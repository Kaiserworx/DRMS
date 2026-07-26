<?php

namespace Tests\Feature\PhaseEight;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\RecipientStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use App\Services\DocumentRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationReadStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_and_unread_state_is_scoped_to_each_users_notification_relationship(): void
    {
        config()->set('queue.default', 'sync');
        $unit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $user = User::factory()->levelOne($unit)->create();
        $otherUser = User::factory()->levelOne($otherUnit)->create();
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

        app(DocumentRoutingService::class)->placeInReceivingBox(
            $levelTwo,
            $recipient,
            $box,
        );
        $notification = $user->notifications()->sole();

        $this->assertNull($notification->read_at);
        $this->assertFalse($otherUser->notifications()->whereKey($notification->id)->exists());

        $notification->markAsRead();
        $this->assertNotNull($notification->fresh()->read_at);
        $notification->markAsUnread();
        $this->assertNull($notification->fresh()->read_at);
    }
}
