<?php

namespace Tests\Feature\PhaseFour;

use App\Enums\RecipientStatus;
use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\RecipientAssignmentService;
use App\Services\RecipientStatusSummary;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecipientAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DeploymentSetting::factory()->create();
    }

    public function test_level_two_assigns_multiple_active_units_atomically(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create();
        $units = OrganizationalUnit::factory()->count(2)->create();

        $created = app(RecipientAssignmentService::class)->assign(
            $actor,
            $document,
            $units->modelKeys(),
            'Distribution list',
        );

        $this->assertCount(2, $created);
        $this->assertDatabaseCount('document_recipients', 2);
        $this->assertSame([
            RecipientStatus::Assigned->value => 2,
            RecipientStatus::ReadyForPickup->value => 0,
            RecipientStatus::ReceivedByRecipientUnit->value => 0,
        ], app(RecipientStatusSummary::class)->for($document));
        $this->assertSame('draft', $document->fresh()->current_status->value);
    }

    public function test_invalid_bulk_assignment_rolls_back_every_recipient(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create();
        $active = OrganizationalUnit::factory()->create();
        $inactive = OrganizationalUnit::factory()->inactive()->create();

        try {
            app(RecipientAssignmentService::class)->assign($actor, $document, [$active->id, $inactive->id]);
            $this->fail('Expected assignment validation to fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('document_recipients', 0);
        }
    }

    public function test_duplicates_are_rejected_by_service_and_database(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create();
        $unit = OrganizationalUnit::factory()->create();
        $service = app(RecipientAssignmentService::class);
        $service->assign($actor, $document, [$unit->id]);

        try {
            $service->assign($actor, $document, [$unit->id]);
            $this->fail('Expected duplicate validation to fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('document_recipients', 1);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $unit->id,
        ]);
    }

    public function test_level_one_cannot_assign_or_remove_recipients(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $actor = User::factory()->levelOne($unit)->create();
        $document = Document::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(RecipientAssignmentService::class)->assign($actor, $document, [$unit->id]);
    }

    public function test_removal_is_allowed_before_and_blocked_after_downstream_activity(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create();
        $removable = DocumentRecipient::factory()->create(['document_id' => $document->id]);
        $blocked = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'date_placed' => now(),
        ]);
        $service = app(RecipientAssignmentService::class);

        $service->remove($actor, $removable);
        $this->assertModelMissing($removable);

        $this->expectException(ValidationException::class);
        $service->remove($actor, $blocked);
    }
}
