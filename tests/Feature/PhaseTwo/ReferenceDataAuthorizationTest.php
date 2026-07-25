<?php

namespace Tests\Feature\PhaseTwo;

use App\Filament\Resources\DocumentOrigins\Pages\ListDocumentOrigins;
use App\Filament\Resources\DocumentTypes\Pages\ListDocumentTypes;
use App\Models\DeploymentSetting;
use App\Models\DocumentOrigin;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DeploymentSetting::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_level_one_can_consume_active_values_but_cannot_open_management_pages(): void
    {
        $levelOne = User::factory()
            ->levelOne(OrganizationalUnit::factory()->create())
            ->create();

        $this->actingAs($levelOne);

        $this->assertTrue($levelOne->can('viewActive', DocumentType::class));
        $this->assertTrue($levelOne->can('viewActive', DocumentOrigin::class));
        $this->get(ListDocumentTypes::getUrl())->assertForbidden();
        $this->get(ListDocumentOrigins::getUrl())->assertForbidden();
    }

    public function test_level_two_can_manage_reference_data(): void
    {
        $levelTwo = User::factory()->levelTwo()->create();

        $this->actingAs($levelTwo);

        $this->get(ListDocumentTypes::getUrl())->assertOk();
        $this->get(ListDocumentOrigins::getUrl())->assertOk();
        $this->assertTrue($levelTwo->can('create', DocumentType::class));
        $this->assertTrue($levelTwo->can('create', DocumentOrigin::class));
    }

    public function test_reference_records_cannot_be_hard_deleted(): void
    {
        $levelTwo = User::factory()->levelTwo()->create();
        $documentType = DocumentType::factory()->create();
        $documentOrigin = DocumentOrigin::factory()->create();

        $this->assertFalse($levelTwo->can('delete', $documentType));
        $this->assertFalse($levelTwo->can('delete', $documentOrigin));
    }
}
