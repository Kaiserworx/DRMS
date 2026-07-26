<?php

namespace Tests\Feature\PhaseSix;

use App\Enums\DocumentStatus;
use App\Enums\OperationalStatus;
use App\Enums\PhysicalLocation;
use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentTransaction;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use App\Services\DocumentRoutingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ReceivingBoxPlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_two_atomically_places_an_eligible_recipient_and_records_exactly_one_transaction(): void
    {
        [$actor, $document, $recipient, $box] = $this->eligiblePlacement();

        $transaction = app(DocumentRoutingService::class)->placeInReceivingBox(
            $actor,
            $recipient,
            $box,
            'Placed during morning distribution',
            ['ip_address' => '127.0.0.1', 'device_info' => 'Phase Six Test Browser'],
        );

        $document->refresh();
        $recipient->refresh();
        $this->assertSame(DocumentStatus::ReadyForPickup, $document->current_status);
        $this->assertSame(PhysicalLocation::ReceivingBox, $document->current_location);
        $this->assertSame(RecipientStatus::ReadyForPickup, $recipient->recipient_status);
        $this->assertSame($box->id, $recipient->receiving_box_id);
        $this->assertNotNull($recipient->date_placed);
        $this->assertSame(RoutingAction::PlaceInReceivingBox, $transaction->action);
        $this->assertSame($recipient->id, $transaction->recipient_id);
        $this->assertSame('127.0.0.1', $transaction->ip_address);
        $this->assertDatabaseCount('document_transactions', 1);
    }

    public function test_level_one_cannot_place_a_document_even_for_own_unit(): void
    {
        [, , $recipient, $box] = $this->eligiblePlacement();
        $levelOne = User::factory()->levelOne($recipient->recipientUnit)->create();

        $this->expectException(AuthorizationException::class);
        app(DocumentRoutingService::class)->placeInReceivingBox($levelOne, $recipient, $box);
    }

    public function test_inactive_or_cross_unit_boxes_are_rejected_without_partial_changes(): void
    {
        [$actor, $document, $recipient, $box] = $this->eligiblePlacement();
        $box->update(['status' => OperationalStatus::Inactive]);

        try {
            app(DocumentRoutingService::class)->placeInReceivingBox($actor, $recipient, $box);
            $this->fail('Expected inactive-box placement validation.');
        } catch (ValidationException) {
            $this->assertSame(DocumentStatus::ForDistribution, $document->fresh()->current_status);
            $this->assertSame(RecipientStatus::Assigned, $recipient->fresh()->recipient_status);
            $this->assertDatabaseCount('document_transactions', 0);
        }

        $otherBox = ReceivingBox::factory()->create();
        $this->expectException(ValidationException::class);
        app(DocumentRoutingService::class)->placeInReceivingBox($actor, $recipient, $otherBox);
    }

    public function test_repeated_placement_is_rejected_without_duplicate_history(): void
    {
        [$actor, , $recipient, $box] = $this->eligiblePlacement();
        $routing = app(DocumentRoutingService::class);
        $routing->placeInReceivingBox($actor, $recipient, $box);

        try {
            $routing->placeInReceivingBox($actor, $recipient, $box);
            $this->fail('Expected repeated placement to fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('document_transactions', 1);
            $this->assertSame(RecipientStatus::ReadyForPickup, $recipient->fresh()->recipient_status);
        }
    }

    public function test_each_recipient_can_be_placed_when_a_multi_recipient_document_is_already_ready(): void
    {
        [$actor, $document, $firstRecipient, $firstBox] = $this->eligiblePlacement();
        $secondUnit = OrganizationalUnit::factory()->create();
        $secondBox = ReceivingBox::factory()->create(['organizational_unit_id' => $secondUnit->id]);
        $secondRecipient = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $secondUnit->id,
        ]);
        $routing = app(DocumentRoutingService::class);

        $routing->placeInReceivingBox($actor, $firstRecipient, $firstBox);
        $routing->placeInReceivingBox($actor, $secondRecipient, $secondBox);

        $this->assertSame(DocumentStatus::ReadyForPickup, $document->fresh()->current_status);
        $this->assertSame(RecipientStatus::ReadyForPickup, $secondRecipient->fresh()->recipient_status);
        $this->assertDatabaseCount('document_transactions', 2);
    }

    public function test_placement_rolls_back_recipient_and_document_when_history_write_fails(): void
    {
        [$actor, $document, $recipient, $box] = $this->eligiblePlacement();

        DocumentTransaction::creating(function (): never {
            throw new RuntimeException('Simulated transaction write failure.');
        });

        try {
            app(DocumentRoutingService::class)->placeInReceivingBox($actor, $recipient, $box);
            $this->fail('Expected transaction write failure.');
        } catch (RuntimeException) {
            $this->assertSame(DocumentStatus::ForDistribution, $document->fresh()->current_status);
            $this->assertSame(PhysicalLocation::ManagingOffice, $document->fresh()->current_location);
            $this->assertSame(RecipientStatus::Assigned, $recipient->fresh()->recipient_status);
            $this->assertNull($recipient->fresh()->receiving_box_id);
            $this->assertNull($recipient->fresh()->date_placed);
            $this->assertDatabaseCount('document_transactions', 0);
        }
    }

    /**
     * @return array{User, Document, DocumentRecipient, ReceivingBox}
     */
    private function eligiblePlacement(): array
    {
        $actor = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();
        $document = Document::factory()->create([
            'current_status' => DocumentStatus::ForDistribution,
            'current_location' => PhysicalLocation::ManagingOffice,
        ]);
        $recipient = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $unit->id,
        ]);
        $box = ReceivingBox::factory()->create([
            'organizational_unit_id' => $unit->id,
        ]);

        return [$actor, $document, $recipient, $box];
    }
}
