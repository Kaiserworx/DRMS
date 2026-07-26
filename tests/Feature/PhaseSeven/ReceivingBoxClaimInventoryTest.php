<?php

namespace Tests\Feature\PhaseSeven;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\RecipientStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingBoxClaimInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_unit_sees_claim_form_other_unit_is_forbidden_and_anonymous_is_redirected(): void
    {
        [$actor, $box, $recipient] = $this->inventoryRecord();
        $other = User::factory()->levelOne()->create();
        $inactive = User::factory()->levelOne($actor->organizationalUnit)->inactive()->create();
        $url = route('receiving-boxes.inventory', ['qrToken' => $box->qr_token]);

        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs($inactive)->get($url)->assertForbidden();
        $this->actingAs($actor)->get($url)
            ->assertOk()
            ->assertSee('Confirm physical receipt')
            ->assertSee('Confirm selected documents')
            ->assertSee('value="'.$recipient->id.'"', false);
    }

    public function test_inventory_excludes_unplaced_not_ready_claimed_and_cancelled_records(): void
    {
        [$actor, $box] = $this->inventoryRecord('Eligible record');
        $cases = [
            ['Assigned record', RecipientStatus::Assigned, now(), DocumentStatus::ReadyForPickup],
            ['Unplaced record', RecipientStatus::ReadyForPickup, null, DocumentStatus::ReadyForPickup],
            ['Claimed record', RecipientStatus::ReceivedByRecipientUnit, now(), DocumentStatus::Completed],
            ['Cancelled record', RecipientStatus::ReadyForPickup, now(), DocumentStatus::Cancelled],
        ];

        foreach ($cases as [$subject, $status, $placedAt, $documentStatus]) {
            $document = Document::factory()->create([
                'subject' => $subject,
                'current_status' => $documentStatus,
                'current_location' => PhysicalLocation::ReceivingBox,
            ]);
            DocumentRecipient::factory()->create([
                'document_id' => $document->id,
                'recipient_unit_id' => $actor->organizational_unit_id,
                'receiving_box_id' => $box->id,
                'recipient_status' => $status,
                'date_placed' => $placedAt,
            ]);
        }

        $response = $this->actingAs($actor)
            ->get(route('receiving-boxes.inventory', ['qrToken' => $box->qr_token]))
            ->assertOk()
            ->assertSee('Eligible record');

        foreach (array_column($cases, 0) as $excludedSubject) {
            $response->assertDontSee($excludedSubject);
        }
    }

    public function test_http_claim_requires_selection_and_derives_claimant_from_session(): void
    {
        [$actor, $box, $recipient] = $this->inventoryRecord();
        $claimUrl = route('receiving-boxes.claim', ['qrToken' => $box->qr_token]);

        $this->actingAs($actor)
            ->post($claimUrl, [
                'receiver_name' => 'Receiver',
                'receiver_position' => 'Custodian',
            ])
            ->assertSessionHasErrors('recipient_ids');

        $this->actingAs($actor)
            ->post($claimUrl, [
                'recipient_ids' => [$recipient->id],
                'receiver_name' => '  Elena Reyes  ',
                'receiver_position' => '  School Registrar  ',
                'claimed_by_user_id' => User::factory()->levelTwo()->create()->id,
            ])
            ->assertRedirect(route('receiving-boxes.inventory', ['qrToken' => $box->qr_token]))
            ->assertSessionHas('status');

        $recipient->refresh();
        $this->assertSame($actor->id, $recipient->claimed_by_user_id);
        $this->assertSame('Elena Reyes', $recipient->received_by_name);
        $this->assertSame('School Registrar', $recipient->received_by_position);
    }

    /**
     * @return array{User, ReceivingBox, DocumentRecipient}
     */
    private function inventoryRecord(string $subject = 'Ready board resolution'): array
    {
        $unit = OrganizationalUnit::factory()->create();
        $actor = User::factory()->levelOne($unit)->create();
        $box = ReceivingBox::factory()->create(['organizational_unit_id' => $unit->id]);
        $document = Document::factory()->create([
            'subject' => $subject,
            'current_status' => DocumentStatus::ReadyForPickup,
            'current_location' => PhysicalLocation::ReceivingBox,
        ]);
        $recipient = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $unit->id,
            'receiving_box_id' => $box->id,
            'recipient_status' => RecipientStatus::ReadyForPickup,
            'date_placed' => now(),
        ]);

        return [$actor, $box, $recipient];
    }
}
