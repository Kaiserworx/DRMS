<?php

namespace Tests\Feature\PhaseFive;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\RoutingAction;
use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentRoutingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecipientRoutingAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DeploymentSetting::factory()->create();
    }

    public function test_recipient_assignment_requires_distribution_state_and_audits_each_recipient_atomically(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create();
        $units = OrganizationalUnit::factory()->count(2)->create();
        $routing = app(DocumentRoutingService::class);

        try {
            $routing->assignRecipients($actor, $document, $units->modelKeys());
            $this->fail('Expected state validation to fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('document_recipients', 0);
            $this->assertDatabaseCount('document_transactions', 0);
        }

        $document->applyRoutingState(DocumentStatus::ForDistribution, PhysicalLocation::ManagingOffice);
        $document->save();

        $recipients = $routing->assignRecipients(
            $actor,
            $document,
            $units->modelKeys(),
            'Approved distribution list',
        );

        $this->assertCount(2, $recipients);
        $this->assertDatabaseCount('document_recipients', 2);
        $this->assertDatabaseCount('document_transactions', 2);
        $this->assertDatabaseHas('document_transactions', [
            'document_id' => $document->id,
            'action' => RoutingAction::AssignRecipientUnit->value,
            'previous_status' => DocumentStatus::ForDistribution->value,
            'new_status' => DocumentStatus::ForDistribution->value,
        ]);
    }

    public function test_level_one_cannot_assign_recipient_units(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $actor = User::factory()->levelOne($unit)->create();
        $document = Document::factory()->create([
            'current_status' => DocumentStatus::ForDistribution,
            'current_location' => PhysicalLocation::ManagingOffice,
        ]);

        $this->expectException(AuthorizationException::class);
        app(DocumentRoutingService::class)->assignRecipients($actor, $document, [$unit->id]);
    }
}
