<?php

namespace Tests\Feature\PhaseEight;

use App\Enums\DocumentNotificationType;
use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentTransaction;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use App\Services\DocumentNotificationDispatcher;
use App\Services\DocumentRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class WorkflowNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_does_not_notify_until_level_two_places_the_document_for_pickup(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $recipientUser = User::factory()->levelOne($unit)->create();
        $inactiveUser = User::factory()->levelOne($unit)->inactive()->create();
        $otherUser = User::factory()->levelOne($otherUnit)->create();
        $levelTwo = User::factory()->levelTwo()->create();
        $document = Document::factory()->create([
            'current_status' => DocumentStatus::ForDistribution,
            'current_location' => PhysicalLocation::ManagingOffice,
        ]);

        app(DocumentRoutingService::class)->assignRecipients(
            $levelTwo,
            $document,
            [$unit->id],
        );

        $this->assertCount(0, $recipientUser->notifications);
        $this->assertCount(0, $inactiveUser->notifications);
        $this->assertCount(0, $otherUser->notifications);
        $this->assertCount(0, $levelTwo->notifications);
        $this->assertDatabaseCount('notification_dispatches', 0);
    }

    public function test_placement_notification_is_deduplicated_for_repeated_dispatch_attempts(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $recipientUser = User::factory()->levelOne($unit)->create();
        $levelTwo = User::factory()->levelTwo()->create();
        $box = ReceivingBox::factory()->create(['organizational_unit_id' => $unit->id]);
        $document = Document::factory()->create([
            'current_status' => DocumentStatus::ForDistribution,
            'current_location' => PhysicalLocation::ManagingOffice,
        ]);
        $recipient = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $unit->id,
            'recipient_status' => RecipientStatus::Assigned,
        ]);

        $transaction = app(DocumentRoutingService::class)->placeInReceivingBox(
            $levelTwo,
            $recipient,
            $box,
        );
        app(DocumentNotificationDispatcher::class)->dispatchForTransaction($transaction);

        $this->assertCount(1, $recipientUser->notifications);
        $this->assertDatabaseCount('notification_dispatches', 1);
        $this->assertSame(
            DocumentNotificationType::Placement->value,
            $recipientUser->notifications->sole()->data['event_type'],
        );
    }

    public function test_status_and_upstream_events_do_not_notify_level_one_users(): void
    {
        $originUnit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $originUser = User::factory()->levelOne($originUnit)->create();
        $otherUser = User::factory()->levelOne($otherUnit)->create();
        $levelTwo = User::factory()->levelTwo()->create();
        $document = Document::factory()->create([
            'submitting_unit_id' => $originUnit->id,
            'current_status' => DocumentStatus::ReceivedAtUpstreamOffice,
            'current_location' => PhysicalLocation::UpstreamOffice,
        ]);

        app(DocumentRoutingService::class)->perform(
            $levelTwo,
            $document,
            RoutingAction::ReturnFromUpstreamOffice,
        );

        $this->assertCount(0, $originUser->notifications);
        $this->assertCount(0, $otherUser->notifications);
        $this->assertDatabaseCount('notification_dispatches', 0);
    }

    public function test_rolled_back_workflow_creates_no_notification_or_dispatch_reservation(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        User::factory()->levelOne($unit)->create();
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create([
            'submitting_unit_id' => $unit->id,
        ]);

        DocumentTransaction::creating(function (): never {
            throw new RuntimeException('Simulated transaction failure.');
        });

        try {
            app(DocumentRoutingService::class)->perform(
                $actor,
                $document,
                RoutingAction::SubmitByUnit,
            );
            $this->fail('Expected the workflow transaction to fail.');
        } catch (RuntimeException) {
            $this->assertSame(DocumentStatus::Draft, $document->fresh()->current_status);
            $this->assertDatabaseCount('notifications', 0);
            $this->assertDatabaseCount('notification_dispatches', 0);
        }
    }

    public function test_notification_delivery_failure_does_not_roll_back_workflow_state(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        User::factory()->levelOne($unit)->create();
        $actor = User::factory()->levelTwo()->create();
        $box = ReceivingBox::factory()->create(['organizational_unit_id' => $unit->id]);
        $document = Document::factory()->create([
            'current_status' => DocumentStatus::ForDistribution,
            'current_location' => PhysicalLocation::ManagingOffice,
        ]);
        $recipient = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $unit->id,
            'recipient_status' => RecipientStatus::Assigned,
        ]);
        Schema::drop('notifications');

        app(DocumentRoutingService::class)->placeInReceivingBox(
            $actor,
            $recipient,
            $box,
        );

        $this->assertSame(DocumentStatus::ReadyForPickup, $document->fresh()->current_status);
        $this->assertDatabaseCount('document_transactions', 1);
        $this->assertDatabaseCount('notification_dispatches', 0);
    }

    public function test_email_channel_is_explicitly_feature_flagged(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $user = User::factory()->levelOne($unit)->create();
        $actor = User::factory()->levelTwo()->create();
        $box = ReceivingBox::factory()->create(['organizational_unit_id' => $unit->id]);
        $document = Document::factory()->create([
            'current_status' => DocumentStatus::ForDistribution,
            'current_location' => PhysicalLocation::ManagingOffice,
        ]);
        $recipient = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $unit->id,
            'recipient_status' => RecipientStatus::Assigned,
        ]);

        Notification::fake();
        config()->set('drms.notifications.email_enabled', true);
        app(DocumentRoutingService::class)->placeInReceivingBox(
            $actor,
            $recipient,
            $box,
        );

        Notification::assertSentTo(
            $user,
            DocumentWorkflowNotification::class,
            fn (DocumentWorkflowNotification $notification, array $channels): bool => $channels === ['database', 'mail']
                && $notification->eventType === DocumentNotificationType::Placement
                && $notification->trackingNumber === $document->tracking_no
                && str_starts_with($notification->safeUrl, config('app.url')),
        );
    }
}
