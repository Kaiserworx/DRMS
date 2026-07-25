<?php

namespace Tests\Feature\PhaseTwo;

use App\Enums\OperationalStatus;
use App\Filament\Resources\DocumentTypes\Pages\CreateDocumentType;
use App\Filament\Resources\DocumentTypes\Pages\EditDocumentType;
use App\Models\DeploymentSetting;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\DocumentTypeSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentTypeAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DeploymentSetting::factory()->create();
        $this->actingAs(User::factory()->levelTwo()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_level_two_can_create_and_deactivate_a_document_type(): void
    {
        Livewire::test(CreateDocumentType::class)
            ->fillForm([
                'name' => '  Board   Resolution ',
                'description' => 'A formal resolution approved by the board.',
                'status' => OperationalStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $documentType = DocumentType::query()
            ->where('normalized_name', 'board resolution')
            ->firstOrFail();

        $this->assertSame('Board Resolution', $documentType->name);
        $this->assertNull($documentType->default_workflow);

        Livewire::test(EditDocumentType::class, ['record' => $documentType->getRouteKey()])
            ->fillForm([
                'status' => OperationalStatus::Inactive->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame(
            OperationalStatus::Inactive,
            $documentType->refresh()->status,
        );
    }

    public function test_normalized_duplicate_document_type_names_are_rejected(): void
    {
        DocumentType::factory()->create([
            'name' => 'Memorandum',
        ]);

        Livewire::test(CreateDocumentType::class)
            ->fillForm([
                'name' => '  memorandum  ',
                'status' => OperationalStatus::Active->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertDatabaseCount('document_types', 1);
    }

    public function test_required_document_type_examples_are_seeded_idempotently(): void
    {
        $this->seed(DocumentTypeSeeder::class);
        $this->seed(DocumentTypeSeeder::class);

        $this->assertDatabaseCount('document_types', count(DocumentTypeSeeder::EXAMPLES));

        foreach (DocumentTypeSeeder::EXAMPLES as $name) {
            $this->assertDatabaseHas('document_types', [
                'name' => $name,
                'status' => OperationalStatus::Active->value,
            ]);
        }
    }

    public function test_inactive_document_types_are_not_selectable_but_remain_readable(): void
    {
        $active = DocumentType::factory()->create(['name' => 'Active Type']);
        $inactive = DocumentType::factory()->inactive()->create(['name' => 'Historical Type']);

        $options = DocumentType::activeOptions();

        $this->assertSame('Active Type', $options[$active->getKey()]);
        $this->assertArrayNotHasKey($inactive->getKey(), $options);
        $this->assertSame('Historical Type', DocumentType::findOrFail($inactive->getKey())->name);
    }
}
