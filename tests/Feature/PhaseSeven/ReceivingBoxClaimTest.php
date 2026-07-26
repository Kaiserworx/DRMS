<?php

namespace Tests\Feature\PhaseSeven;

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
use App\Services\ReceivingBoxClaimService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class ReceivingBoxClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_claim_derives_identity_and_records_complete_audit_context(): void
    {
        [$actor, $box, $document, $recipient] = $this->eligibleClaim();

        $transactions = app(ReceivingBoxClaimService::class)->claim(
            $actor,
            $box,
            [$recipient->id],
            'Maria Santos',
            'Records Custodian',
            'Physical copy received intact.',
            ['ip_address' => '127.0.0.1', 'device_info' => 'Phase Seven Test Browser'],
        );

        $recipient->refresh();
        $document->refresh();
        $transaction = $transactions->sole();
        $this->assertSame(RecipientStatus::ReceivedByRecipientUnit, $recipient->recipient_status);
        $this->assertSame($actor->id, $recipient->claimed_by_user_id);
        $this->assertSame('Maria Santos', $recipient->received_by_name);
        $this->assertSame('Records Custodian', $recipient->received_by_position);
        $this->assertNotNull($recipient->date_claimed);
        $this->assertSame(DocumentStatus::Completed, $document->current_status);
        $this->assertSame(PhysicalLocation::RecipientUnit, $document->current_location);
        $this->assertSame(RoutingAction::ClaimByRecipientUnit, $transaction->action);
        $this->assertSame('Maria Santos', $transaction->receiver_name);
        $this->assertSame('Records Custodian', $transaction->receiver_position);
        $this->assertSame($actor->id, $transaction->performed_by);
        $this->assertSame('127.0.0.1', $transaction->ip_address);
        $this->assertSame('Phase Seven Test Browser', $transaction->device_info);
    }

    public function test_one_submission_claims_multiple_documents_atomically(): void
    {
        [$actor, $box, $firstDocument, $firstRecipient] = $this->eligibleClaim();
        $secondDocument = Document::factory()->create([
            'current_status' => DocumentStatus::ReadyForPickup,
            'current_location' => PhysicalLocation::ReceivingBox,
        ]);
        $secondRecipient = DocumentRecipient::factory()->create([
            'document_id' => $secondDocument->id,
            'recipient_unit_id' => $actor->organizational_unit_id,
            'receiving_box_id' => $box->id,
            'recipient_status' => RecipientStatus::ReadyForPickup,
            'date_placed' => now(),
        ]);

        $transactions = app(ReceivingBoxClaimService::class)->claim(
            $actor,
            $box,
            [$firstRecipient->id, $secondRecipient->id],
            'Juan Dela Cruz',
            'Administrative Assistant',
        );

        $this->assertCount(2, $transactions);
        $this->assertSame(DocumentStatus::Completed, $firstDocument->fresh()->current_status);
        $this->assertSame(DocumentStatus::Completed, $secondDocument->fresh()->current_status);
        $this->assertDatabaseCount('document_transactions', 2);
    }

    public function test_empty_already_claimed_and_repeated_selections_are_rejected_without_duplicates(): void
    {
        [$actor, $box, , $recipient] = $this->eligibleClaim();
        $service = app(ReceivingBoxClaimService::class);

        try {
            $service->claim($actor, $box, [], 'Receiver', 'Position');
            $this->fail('Expected empty selection validation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('document_transactions', 0);
        }

        $service->claim($actor, $box, [$recipient->id], 'Receiver', 'Position');

        try {
            $service->claim($actor, $box, [$recipient->id], 'Receiver', 'Position');
            $this->fail('Expected repeated claim validation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('document_transactions', 1);
            $this->assertSame(RecipientStatus::ReceivedByRecipientUnit, $recipient->fresh()->recipient_status);
        }
    }

    public function test_cross_unit_manipulation_and_level_two_claims_are_forbidden(): void
    {
        [$actor, $box, , $recipient] = $this->eligibleClaim();
        [, , , $otherRecipient] = $this->eligibleClaim();

        try {
            app(ReceivingBoxClaimService::class)->claim(
                $actor,
                $box,
                [$recipient->id, $otherRecipient->id],
                'Receiver',
                'Position',
            );
            $this->fail('Expected cross-unit selection validation.');
        } catch (ValidationException) {
            $this->assertSame(RecipientStatus::ReadyForPickup, $recipient->fresh()->recipient_status);
            $this->assertDatabaseCount('document_transactions', 0);
        }

        $this->expectException(AuthorizationException::class);
        app(ReceivingBoxClaimService::class)->claim(
            User::factory()->levelTwo()->create(),
            $box,
            [$recipient->id],
            'Receiver',
            'Position',
        );
    }

    public function test_document_is_partial_until_every_recipient_unit_finishes_then_completed(): void
    {
        [$firstActor, $firstBox, $document, $firstRecipient] = $this->eligibleClaim();
        $secondUnit = OrganizationalUnit::factory()->create();
        $secondActor = User::factory()->levelOne($secondUnit)->create();
        $secondBox = ReceivingBox::factory()->create(['organizational_unit_id' => $secondUnit->id]);
        $secondRecipient = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $secondUnit->id,
            'receiving_box_id' => $secondBox->id,
            'recipient_status' => RecipientStatus::ReadyForPickup,
            'date_placed' => now(),
        ]);
        $service = app(ReceivingBoxClaimService::class);

        $service->claim($firstActor, $firstBox, [$firstRecipient->id], 'First Receiver', 'Principal');
        $this->assertSame(DocumentStatus::PartiallyClaimed, $document->fresh()->current_status);
        $this->assertSame(PhysicalLocation::ReceivingBox, $document->fresh()->current_location);

        $service->claim($secondActor, $secondBox, [$secondRecipient->id], 'Second Receiver', 'Principal');
        $this->assertSame(DocumentStatus::Completed, $document->fresh()->current_status);
        $this->assertSame(PhysicalLocation::RecipientUnit, $document->fresh()->current_location);
        $this->assertSame(
            [DocumentStatus::PartiallyClaimed, DocumentStatus::Completed],
            DocumentTransaction::query()->orderBy('id')->get()->pluck('new_status')->all(),
        );
    }

    public function test_batch_rolls_back_every_recipient_and_document_when_a_transaction_write_fails(): void
    {
        [$actor, $box, $document, $recipient] = $this->eligibleClaim();

        DocumentTransaction::creating(function (): never {
            throw new RuntimeException('Simulated claim audit failure.');
        });

        try {
            app(ReceivingBoxClaimService::class)->claim(
                $actor,
                $box,
                [$recipient->id],
                'Receiver',
                'Position',
            );
            $this->fail('Expected claim audit failure.');
        } catch (RuntimeException) {
            $this->assertSame(RecipientStatus::ReadyForPickup, $recipient->fresh()->recipient_status);
            $this->assertNull($recipient->fresh()->date_claimed);
            $this->assertSame(DocumentStatus::ReadyForPickup, $document->fresh()->current_status);
            $this->assertDatabaseCount('document_transactions', 0);
        }
    }

    public function test_direct_recipient_claim_mutation_is_rejected(): void
    {
        [, , , $recipient] = $this->eligibleClaim();

        $this->expectException(LogicException::class);
        $recipient->recipient_status = RecipientStatus::ReceivedByRecipientUnit;
        $recipient->save();
    }

    /**
     * @return array{User, ReceivingBox, Document, DocumentRecipient}
     */
    private function eligibleClaim(): array
    {
        $unit = OrganizationalUnit::factory()->create();
        $actor = User::factory()->levelOne($unit)->create();
        $box = ReceivingBox::factory()->create(['organizational_unit_id' => $unit->id]);
        $document = Document::factory()->create([
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

        return [$actor, $box, $document, $recipient];
    }
}
