<?php

namespace Tests\Feature\PhaseTwo;

use App\Enums\OperationalStatus;
use App\Enums\OriginType;
use App\Filament\Resources\DocumentOrigins\Pages\CreateDocumentOrigin;
use App\Models\DeploymentSetting;
use App\Models\DocumentOrigin;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentOriginAdministrationTest extends TestCase
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

    public function test_level_two_can_create_a_document_origin(): void
    {
        Livewire::test(CreateDocumentOrigin::class)
            ->fillForm([
                'origin_type' => OriginType::UpstreamOffice->value,
                'origin_name' => '  Provincial   Division Office ',
                'office_code' => 'pdo-01',
                'address' => 'Government Center',
                'status' => OperationalStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('document_origins', [
            'origin_type' => OriginType::UpstreamOffice->value,
            'origin_name' => 'Provincial Division Office',
            'normalized_name' => 'provincial division office',
            'office_code' => 'PDO-01',
        ]);
    }

    public function test_normalized_duplicate_origin_names_are_rejected_within_the_same_type(): void
    {
        DocumentOrigin::factory()->create([
            'origin_type' => OriginType::UpstreamOffice,
            'origin_name' => 'Division Office',
        ]);

        Livewire::test(CreateDocumentOrigin::class)
            ->fillForm([
                'origin_type' => OriginType::UpstreamOffice->value,
                'origin_name' => '  division   office ',
                'status' => OperationalStatus::Active->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['origin_name']);

        $this->assertDatabaseCount('document_origins', 1);
    }

    public function test_same_normalized_origin_name_is_allowed_for_a_different_origin_type(): void
    {
        DocumentOrigin::factory()->create([
            'origin_type' => OriginType::UpstreamOffice,
            'origin_name' => 'Records Office',
        ]);

        Livewire::test(CreateDocumentOrigin::class)
            ->fillForm([
                'origin_type' => OriginType::ManagingOffice->value,
                'origin_name' => ' records office ',
                'status' => OperationalStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('document_origins', 2);
    }

    public function test_inactive_document_origins_are_not_selectable_but_remain_readable(): void
    {
        $active = DocumentOrigin::factory()->create(['origin_name' => 'Active Origin']);
        $inactive = DocumentOrigin::factory()->inactive()->create(['origin_name' => 'Historical Origin']);

        $options = DocumentOrigin::activeOptions();

        $this->assertSame('Active Origin', $options[$active->getKey()]);
        $this->assertArrayNotHasKey($inactive->getKey(), $options);
        $this->assertSame(
            'Historical Origin',
            DocumentOrigin::findOrFail($inactive->getKey())->origin_name,
        );
    }
}
