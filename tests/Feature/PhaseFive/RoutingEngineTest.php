<?php

namespace Tests\Feature\PhaseFive;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\RoutingAction;
use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\DocumentTransaction;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentRoutingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class RoutingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DeploymentSetting::factory()->create([
            'managing_office_name' => 'District Office',
            'upstream_office_label' => 'Division Office',
            'level_1_unit_label' => 'School',
            'receiving_box_label' => 'School Box',
        ]);
    }

    public function test_complete_managing_and_upstream_route_records_exactly_one_transaction_per_action(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create([
            'current_status' => DocumentStatus::Draft,
            'current_location' => PhysicalLocation::OrganizationalUnit,
        ]);
        $routing = app(DocumentRoutingService::class);

        $steps = [
            [RoutingAction::SubmitByUnit, DocumentStatus::Submitted, PhysicalLocation::ManagingOffice],
            [RoutingAction::ReceiveAtManagingOffice, DocumentStatus::ReceivedAtManagingOffice, PhysicalLocation::ManagingOffice],
            [RoutingAction::ForwardToUpstreamOffice, DocumentStatus::ForwardedToUpstreamOffice, PhysicalLocation::UpstreamOffice],
            [RoutingAction::RecordUpstreamReceipt, DocumentStatus::ReceivedAtUpstreamOffice, PhysicalLocation::UpstreamOffice],
            [RoutingAction::ReturnFromUpstreamOffice, DocumentStatus::ReturnedFromUpstreamOffice, PhysicalLocation::ManagingOffice],
            [RoutingAction::MarkForDistribution, DocumentStatus::ForDistribution, PhysicalLocation::ManagingOffice],
        ];

        foreach ($steps as [$action, $expectedStatus, $expectedLocation]) {
            $transaction = $routing->perform($actor, $document, $action, "Completed {$action->label()}");
            $document->refresh();

            $this->assertSame($expectedStatus, $document->current_status);
            $this->assertSame($expectedLocation, $document->current_location);
            $this->assertSame($action, $transaction->action);
        }

        $this->assertDatabaseCount('document_transactions', count($steps));
    }

    public function test_level_one_can_only_submit_a_draft_from_own_unit(): void
    {
        $ownUnit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $actor = User::factory()->levelOne($ownUnit)->create();
        $own = Document::factory()->create([
            'submitting_unit_id' => $ownUnit->id,
            'current_location' => PhysicalLocation::OrganizationalUnit,
        ]);
        $other = Document::factory()->create([
            'submitting_unit_id' => $otherUnit->id,
            'current_location' => PhysicalLocation::OrganizationalUnit,
        ]);
        $routing = app(DocumentRoutingService::class);

        $routing->perform($actor, $own, RoutingAction::SubmitByUnit);
        $this->assertSame(DocumentStatus::Submitted, $own->fresh()->current_status);

        $this->expectException(AuthorizationException::class);
        $routing->perform($actor, $other, RoutingAction::SubmitByUnit);
    }

    public function test_level_one_cannot_execute_managing_office_action(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $actor = User::factory()->levelOne($unit)->create();
        $document = Document::factory()->create([
            'submitting_unit_id' => $unit->id,
            'current_status' => DocumentStatus::Submitted,
            'current_location' => PhysicalLocation::ManagingOffice,
        ]);

        $this->expectException(AuthorizationException::class);
        app(DocumentRoutingService::class)->perform(
            $actor,
            $document,
            RoutingAction::ReceiveAtManagingOffice,
        );
    }

    public function test_invalid_and_repeated_actions_change_nothing_and_create_no_duplicate_history(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create();
        $routing = app(DocumentRoutingService::class);
        $routing->perform($actor, $document, RoutingAction::SubmitByUnit);

        try {
            $routing->perform($actor, $document, RoutingAction::SubmitByUnit);
            $this->fail('Expected repeated routing action to fail.');
        } catch (ValidationException) {
            $this->assertSame(DocumentStatus::Submitted, $document->fresh()->current_status);
            $this->assertDatabaseCount('document_transactions', 1);
        }

        try {
            $routing->perform($actor, $document, RoutingAction::MarkForDistribution);
            $this->fail('Expected out-of-sequence routing action to fail.');
        } catch (ValidationException) {
            $this->assertSame(DocumentStatus::Submitted, $document->fresh()->current_status);
            $this->assertDatabaseCount('document_transactions', 1);
        }
    }

    public function test_cancellation_requires_reason_and_records_reserved_fields(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create();
        $routing = app(DocumentRoutingService::class);

        try {
            $routing->perform($actor, $document, RoutingAction::Cancel);
            $this->fail('Expected cancellation reason validation.');
        } catch (ValidationException) {
            $this->assertSame(DocumentStatus::Draft, $document->fresh()->current_status);
            $this->assertDatabaseCount('document_transactions', 0);
        }

        $routing->perform($actor, $document, RoutingAction::Cancel, 'Duplicate physical submission');
        $document->refresh();

        $this->assertSame(DocumentStatus::Cancelled, $document->current_status);
        $this->assertSame($actor->id, $document->cancelled_by);
        $this->assertSame('Duplicate physical submission', $document->cancellation_reason);
        $this->assertNotNull($document->cancelled_at);
        $this->assertDatabaseCount('document_transactions', 1);
    }

    public function test_state_change_rolls_back_if_transaction_insert_fails(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create();

        DocumentTransaction::creating(function (): never {
            throw new RuntimeException('Simulated transaction write failure.');
        });

        try {
            app(DocumentRoutingService::class)->perform($actor, $document, RoutingAction::SubmitByUnit);
            $this->fail('Expected transaction write failure.');
        } catch (RuntimeException) {
            $this->assertSame(DocumentStatus::Draft, $document->fresh()->current_status);
            $this->assertDatabaseCount('document_transactions', 0);
        }
    }

    public function test_direct_state_mutation_and_history_mutation_are_rejected(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create();

        try {
            $document->current_status = DocumentStatus::Submitted;
            $document->save();
            $this->fail('Expected direct state mutation to fail.');
        } catch (LogicException) {
            $this->assertSame(DocumentStatus::Draft, $document->fresh()->current_status);
        }

        $transaction = app(DocumentRoutingService::class)->perform(
            $actor,
            $document,
            RoutingAction::SubmitByUnit,
        );

        $this->expectException(LogicException::class);
        $transaction->remarks = 'Rewritten';
        $transaction->save();
    }

    public function test_generic_routing_action_rejects_placement_without_recipient_context(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create([
            'current_status' => DocumentStatus::ForDistribution,
            'current_location' => PhysicalLocation::ManagingOffice,
        ]);

        $this->expectException(ValidationException::class);
        app(DocumentRoutingService::class)->perform(
            $actor,
            $document,
            RoutingAction::PlaceInReceivingBox,
        );
    }

    public function test_optional_workflow_branches_are_supported(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $routing = app(DocumentRoutingService::class);
        $forwarded = Document::factory()->create([
            'current_status' => DocumentStatus::ForwardedToUpstreamOffice,
            'current_location' => PhysicalLocation::UpstreamOffice,
        ]);
        $received = Document::factory()->create([
            'current_status' => DocumentStatus::ReceivedAtManagingOffice,
            'current_location' => PhysicalLocation::ManagingOffice,
        ]);

        $routing->perform($actor, $forwarded, RoutingAction::ReturnFromUpstreamOffice);
        $routing->perform($actor, $received, RoutingAction::MarkForDistribution);

        $this->assertSame(DocumentStatus::ReturnedFromUpstreamOffice, $forwarded->fresh()->current_status);
        $this->assertSame(DocumentStatus::ForDistribution, $received->fresh()->current_status);
        $this->assertDatabaseCount('document_transactions', 2);
    }

    public function test_transaction_context_is_recorded_and_history_cannot_be_deleted(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create();

        $transaction = app(DocumentRoutingService::class)->perform(
            $actor,
            $document,
            RoutingAction::SubmitByUnit,
            'Submitted from browser',
            [
                'ip_address' => '127.0.0.1',
                'device_info' => 'Phase Five Test Browser',
            ],
        );

        $this->assertSame('127.0.0.1', $transaction->ip_address);
        $this->assertSame('Phase Five Test Browser', $transaction->device_info);

        $this->expectException(LogicException::class);
        $transaction->delete();
    }
}
