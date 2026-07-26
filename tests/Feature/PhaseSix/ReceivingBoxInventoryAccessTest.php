<?php

namespace Tests\Feature\PhaseSix;

use App\Enums\OperationalStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingBoxInventoryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_token_lookup_requires_authentication(): void
    {
        $box = ReceivingBox::factory()->create();

        $this->get(route('receiving-boxes.inventory', ['qrToken' => $box->qr_token]))
            ->assertRedirect('/admin/login');
    }

    public function test_level_one_can_view_only_own_unit_inventory_and_level_two_can_preview_any_box(): void
    {
        $ownUnit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $ownBox = ReceivingBox::factory()->create(['organizational_unit_id' => $ownUnit->id]);
        $otherBox = ReceivingBox::factory()->create(['organizational_unit_id' => $otherUnit->id]);
        $levelOne = User::factory()->levelOne($ownUnit)->create();
        $levelTwo = User::factory()->levelTwo()->create();

        $this->actingAs($levelOne)
            ->get(route('receiving-boxes.inventory', ['qrToken' => $ownBox->qr_token]))
            ->assertOk()
            ->assertSee($ownUnit->unit_name);
        $this->actingAs($levelOne)
            ->get(route('receiving-boxes.inventory', ['qrToken' => $otherBox->qr_token]))
            ->assertForbidden();
        $this->actingAs($levelTwo)
            ->get(route('receiving-boxes.inventory', ['qrToken' => $otherBox->qr_token]))
            ->assertOk()
            ->assertSee($otherUnit->unit_name);
    }

    public function test_invalid_obsolete_and_inactive_tokens_fail_safely(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $inactive = ReceivingBox::factory()->inactive()->create();
        $inactiveUnitBox = ReceivingBox::factory()->create();
        $inactiveUnitBox->organizationalUnit->update(['status' => OperationalStatus::Inactive]);

        $this->actingAs($actor)
            ->get('/receiving-boxes/'.str_repeat('x', 64))
            ->assertNotFound();
        $this->actingAs($actor)
            ->get(route('receiving-boxes.inventory', ['qrToken' => $inactive->qr_token]))
            ->assertNotFound();
        $this->actingAs($actor)
            ->get(route('receiving-boxes.inventory', ['qrToken' => $inactiveUnitBox->qr_token]))
            ->assertNotFound();
    }

    public function test_inventory_shows_only_records_ready_in_that_box(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $box = ReceivingBox::factory()->create(['organizational_unit_id' => $unit->id]);
        $actor = User::factory()->levelOne($unit)->create();
        $readyDocument = Document::factory()->create(['subject' => 'Ready board resolution']);
        $assignedDocument = Document::factory()->create(['subject' => 'Not yet placed']);

        DocumentRecipient::factory()->create([
            'document_id' => $readyDocument->id,
            'recipient_unit_id' => $unit->id,
            'receiving_box_id' => $box->id,
            'recipient_status' => 'ready_for_pickup',
            'date_placed' => now(),
        ]);
        DocumentRecipient::factory()->create([
            'document_id' => $assignedDocument->id,
            'recipient_unit_id' => $unit->id,
        ]);

        $this->actingAs($actor)
            ->get(route('receiving-boxes.inventory', ['qrToken' => $box->qr_token]))
            ->assertOk()
            ->assertSee('Ready board resolution')
            ->assertDontSee('Not yet placed')
            ->assertSee('Confirm physical receipt');
    }

    public function test_inactive_box_remains_manageable_by_level_two_but_cannot_be_scanned(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $box = ReceivingBox::factory()->create([
            'status' => OperationalStatus::Inactive,
        ]);

        $this->actingAs($actor)
            ->get("/admin/receiving-boxes/{$box->id}")
            ->assertOk()
            ->assertSee('Inactive');
        $this->actingAs($actor)
            ->get(route('receiving-boxes.inventory', ['qrToken' => $box->qr_token]))
            ->assertNotFound();
    }

    public function test_level_two_can_render_the_printable_permanent_qr_label(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $box = ReceivingBox::factory()->create([
            'box_location' => 'Records counter',
        ]);

        $this->actingAs($actor)
            ->get(route('receiving-boxes.label', $box))
            ->assertOk()
            ->assertSee('Print permanent label')
            ->assertSee('Records counter')
            ->assertSee('<svg', false)
            ->assertSee('does not authorize access or confirm document claims');
    }
}
