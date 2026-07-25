<?php

namespace Tests\Feature\PhaseFour;

use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipientVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DeploymentSetting::factory()->create();
    }

    public function test_level_one_only_sees_own_recipient_row_and_related_document(): void
    {
        $ownUnit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $actor = User::factory()->levelOne($ownUnit)->create();
        $document = Document::factory()->create(['submitting_unit_id' => null]);
        $ownRecipient = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $ownUnit->id,
        ]);
        $otherRecipient = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $otherUnit->id,
        ]);

        $visibleDocuments = Document::query()->visibleTo($actor)->get();
        $visibleRecipients = DocumentRecipient::query()->visibleTo($actor)->get();

        $this->assertTrue($visibleDocuments->contains($document));
        $this->assertTrue($visibleRecipients->contains($ownRecipient));
        $this->assertFalse($visibleRecipients->contains($otherRecipient));
        $this->assertTrue($actor->can('view', $ownRecipient));
        $this->assertFalse($actor->can('view', $otherRecipient));
    }
}
