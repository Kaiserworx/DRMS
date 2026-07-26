<?php

namespace Tests\Feature\PhaseTwelve;

use App\Enums\DocumentStatus;
use App\Enums\OriginType;
use App\Enums\PhysicalLocation;
use App\Enums\Priority;
use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\DocumentOrigin;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use App\Services\DocumentRegistrationService;
use App\Services\DocumentRoutingService;
use App\Services\OperationalReportService;
use App\Services\RecipientAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowAcceptanceUatTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_two_creates_upstream_appointment_and_multi_unit_book_delivery_records(): void
    {
        DeploymentSetting::factory()->create();
        $actor = User::factory()->levelTwo()->create();
        $origin = DocumentOrigin::factory()->create([
            'origin_type' => OriginType::UpstreamOffice,
            'origin_name' => 'Upstream Office',
        ]);
        $appointmentType = DocumentType::factory()->create(['name' => 'Appointment']);
        $bookDeliveryType = DocumentType::factory()->create(['name' => 'Book Delivery']);
        $firstUnit = OrganizationalUnit::factory()->create();
        $secondUnit = OrganizationalUnit::factory()->create();
        $registration = app(DocumentRegistrationService::class);

        $appointment = $registration->register($actor, $this->registrationInput(
            $appointmentType->id,
            'Upstream appointment record',
            ['origin_id' => $origin->id, 'origin_reference_no' => 'UPSTREAM-APT-001'],
        ));
        $bookDelivery = $registration->register($actor, $this->registrationInput(
            $bookDeliveryType->id,
            'Multi-unit book delivery',
        ));
        $recipients = app(RecipientAssignmentService::class)->assign(
            $actor,
            $bookDelivery,
            [$firstUnit->id, $secondUnit->id],
        );

        $this->assertSame('Appointment', $appointment->documentType->name);
        $this->assertSame($origin->id, $appointment->origin_id);
        $this->assertSame('UPSTREAM-APT-001', $appointment->origin_reference_no);
        $this->assertSame('Book Delivery', $bookDelivery->documentType->name);
        $this->assertEqualsCanonicalizing(
            [$firstUnit->id, $secondUnit->id],
            $recipients->pluck('recipient_unit_id')->all(),
        );
    }

    public function test_managing_office_generates_pending_pickup_and_completed_reports(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();
        $box = ReceivingBox::factory()->create(['organizational_unit_id' => $unit->id]);
        $pending = Document::factory()->create([
            'current_status' => DocumentStatus::ReadyForPickup,
            'current_location' => PhysicalLocation::ReceivingBox,
        ]);
        $completed = Document::factory()->create([
            'current_status' => DocumentStatus::Completed,
            'current_location' => PhysicalLocation::OrganizationalUnit,
        ]);

        DocumentRecipient::factory()->create([
            'document_id' => $pending->id,
            'recipient_unit_id' => $unit->id,
            'receiving_box_id' => $box->id,
            'recipient_status' => RecipientStatus::ReadyForPickup,
            'date_placed' => now()->subHour(),
        ]);
        DocumentRecipient::factory()->create([
            'document_id' => $completed->id,
            'recipient_unit_id' => $unit->id,
            'receiving_box_id' => $box->id,
            'recipient_status' => RecipientStatus::ReceivedByRecipientUnit,
            'date_placed' => now()->subHours(2),
            'date_claimed' => now()->subHour(),
            'claimed_by_user_id' => User::factory()->levelOne($unit),
            'received_by_name' => 'Pilot Receiver',
            'received_by_position' => 'Records Custodian',
        ]);

        $reports = app(OperationalReportService::class);
        $pendingRows = $reports->paginate($actor, 'unclaimed_documents', [])['rows'];
        $completedRows = $reports->paginate($actor, 'claimed_documents', [])['rows'];

        $this->assertSame(
            [$pending->tracking_no],
            collect($pendingRows->items())->pluck('tracking_no')->all(),
        );
        $this->assertSame(
            [$completed->tracking_no],
            collect($completedRows->items())->pluck('tracking_no')->all(),
        );
    }

    public function test_cancellation_remains_in_the_append_only_document_history(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create();

        $transaction = app(DocumentRoutingService::class)->perform(
            $actor,
            $document,
            RoutingAction::Cancel,
            'UAT duplicate-record cancellation',
        );

        $this->assertSame(RoutingAction::Cancel, $transaction->action);
        $this->assertSame(DocumentStatus::Cancelled, $transaction->new_status);
        $this->assertSame('UAT duplicate-record cancellation', $transaction->remarks);
        $this->assertTrue(
            $document->transactions()
                ->whereKey($transaction)
                ->where('action', RoutingAction::Cancel->value)
                ->exists(),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationInput(int $typeId, string $subject, array $overrides = []): array
    {
        return array_merge([
            'document_type_id' => $typeId,
            'subject' => $subject,
            'description' => null,
            'origin_id' => null,
            'origin_reference_no' => null,
            'submitting_unit_id' => null,
            'priority' => Priority::Normal->value,
            'initial_status' => DocumentStatus::Draft->value,
            'date_received' => null,
            'due_date' => null,
            'remarks' => null,
        ], $overrides);
    }
}
