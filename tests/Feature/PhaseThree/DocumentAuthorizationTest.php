<?php

namespace Tests\Feature\PhaseThree;

use App\Enums\Priority;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DeploymentSetting::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_level_one_can_only_view_documents_submitted_by_own_unit(): void
    {
        $ownUnit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $actor = User::factory()->levelOne($ownUnit)->create();
        $ownDocument = Document::factory()->create(['submitting_unit_id' => $ownUnit->id]);
        $otherDocument = Document::factory()->create(['submitting_unit_id' => $otherUnit->id]);

        $this->actingAs($actor);

        $this->get(ListDocuments::getUrl())->assertOk();
        $this->get(ViewDocument::getUrl(['record' => $ownDocument]))->assertOk();
        $this->get(ViewDocument::getUrl(['record' => $otherDocument]))->assertNotFound();
    }

    public function test_level_two_can_view_documents_from_any_unit(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create([
            'submitting_unit_id' => OrganizationalUnit::factory()->create()->id,
        ]);

        $this->actingAs($actor);

        $this->get(ViewDocument::getUrl(['record' => $document]))->assertOk();
    }

    public function test_level_two_returns_to_the_document_list_after_registration(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $type = DocumentType::factory()->create();
        $this->actingAs($actor);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'document_type_id' => $type->id,
                'subject' => 'Redirect after registration',
                'priority' => Priority::Normal->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect(DocumentResource::getUrl('index'));
    }
}
