<?php

namespace Tests\Feature\PhaseTwelve;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use App\Services\DocumentRoutingService;
use Filament\Facades\Filament;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListingAndNotificationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DeploymentSetting::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_level_one_and_level_two_document_lists_show_latest_records_first(): void
    {
        $ownUnit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $levelOne = User::factory()->levelOne($ownUnit)->create();
        $levelTwo = User::factory()->levelTwo()->create();
        $olderOwn = Document::factory()->create([
            'submitting_unit_id' => $ownUnit->id,
            'created_at' => '2026-07-24 08:00:00',
        ]);
        $latestOwn = Document::factory()->create([
            'submitting_unit_id' => $ownUnit->id,
            'created_at' => '2026-07-25 08:00:00',
        ]);
        $latestOther = Document::factory()->create([
            'submitting_unit_id' => $otherUnit->id,
            'created_at' => '2026-07-26 08:00:00',
        ]);

        $levelOneList = Livewire::actingAs($levelOne)
            ->test(ListDocuments::class);

        $levelOneList
            ->assertCanSeeTableRecords([$latestOwn, $olderOwn], inOrder: true)
            ->assertCanNotSeeTableRecords([$latestOther]);

        $this->assertSame([
            'tracking_no',
            'current_status',
            'subject',
            'documentType.name',
            'submittingUnit.unit_name',
            'priority',
            'created_at',
        ], array_keys($levelOneList->instance()->getTable()->getColumns()));

        $levelTwoList = Livewire::actingAs($levelTwo)
            ->test(ListDocuments::class);

        $levelTwoList
            ->assertCanSeeTableRecords([$latestOther, $latestOwn, $olderOwn], inOrder: true);

        $this->assertSame([
            'tracking_no',
            'subject',
            'documentType.name',
            'submittingUnit.unit_name',
            'priority',
            'current_status',
            'created_at',
        ], array_keys($levelTwoList->instance()->getTable()->getColumns()));
    }

    public function test_south_campus_notification_remains_after_reading_until_its_user_deletes_it(): void
    {
        config()->set('queue.default', 'sync');
        $southCampus = OrganizationalUnit::factory()->create([
            'unit_name' => 'South Campus School',
        ]);
        $southUser = User::factory()->levelOne($southCampus)->create();
        $otherUser = User::factory()->levelOne(OrganizationalUnit::factory()->create())->create();
        $levelTwo = User::factory()->levelTwo()->create();
        $box = ReceivingBox::factory()->create(['organizational_unit_id' => $southCampus->id]);
        $document = Document::factory()->create([
            'current_status' => DocumentStatus::ForDistribution,
            'current_location' => PhysicalLocation::ManagingOffice,
        ]);

        $recipients = app(DocumentRoutingService::class)->assignRecipients(
            $levelTwo,
            $document,
            [$southCampus->id],
        );

        $this->assertCount(0, $southUser->notifications);

        /** @var DocumentRecipient $recipient */
        $recipient = $recipients->sole();
        app(DocumentRoutingService::class)->placeInReceivingBox(
            $levelTwo,
            $recipient,
            $box,
        );

        $notification = $southUser->notifications()->sole();

        Livewire::actingAs($southUser)
            ->test(DatabaseNotifications::class)
            ->assertSee('Document ready for pickup')
            ->call('markNotificationAsRead', $notification->id)
            ->assertSee('Document ready for pickup');

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(1, $southUser->notifications()->count());

        Livewire::actingAs($otherUser)
            ->test(DatabaseNotifications::class)
            ->call('removeNotification', $notification->id);

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);

        Livewire::actingAs($southUser)
            ->test(DatabaseNotifications::class)
            ->call('removeNotification', $notification->id);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }
}
