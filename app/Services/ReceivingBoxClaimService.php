<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\OperationalStatus;
use App\Enums\PhysicalLocation;
use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentTransaction;
use App\Models\ReceivingBox;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivingBoxClaimService
{
    public function __construct(
        private readonly DocumentNotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array<int, int|string>  $recipientIds
     * @param  array{ip_address?: ?string, device_info?: ?string}  $context
     * @return Collection<int, DocumentTransaction>
     */
    public function claim(
        User $actor,
        ReceivingBox $box,
        array $recipientIds,
        string $receiverName,
        string $receiverPosition,
        ?string $remarks = null,
        array $context = [],
    ): Collection {
        if ($actor->isLevelTwo() || $actor->organizational_unit_id !== $box->organizational_unit_id) {
            throw new AuthorizationException('Only the Level 1 user assigned to this receiving box may confirm receipt.');
        }

        if ($actor->status !== OperationalStatus::Active) {
            throw new AuthorizationException('Inactive users may not confirm receipt.');
        }

        $ids = collect($recipientIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values();
        $receiverName = trim($receiverName);
        $receiverPosition = trim($receiverPosition);
        $remarks = filled($remarks) ? trim((string) $remarks) : null;

        $errors = [];
        if ($ids->isEmpty()) {
            $errors['recipient_ids'] = 'Select at least one document to confirm.';
        }
        if ($receiverName === '' || mb_strlen($receiverName) > 255) {
            $errors['receiver_name'] = 'Enter a receiver name of no more than 255 characters.';
        }
        if ($receiverPosition === '' || mb_strlen($receiverPosition) > 255) {
            $errors['receiver_position'] = 'Enter a receiver position or designation of no more than 255 characters.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $transactions = DB::transaction(function () use (
            $actor,
            $box,
            $ids,
            $receiverName,
            $receiverPosition,
            $remarks,
            $context,
        ): Collection {
            $documentIds = DocumentRecipient::query()
                ->whereKey($ids->all())
                ->pluck('document_id')
                ->unique()
                ->sort()
                ->values();

            if ($documentIds->isEmpty()) {
                throw $this->invalidSelection();
            }

            /** @var Collection<int, Document> $documents */
            $documents = Document::query()
                ->whereKey($documentIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Collection<int, DocumentRecipient> $recipients */
            $recipients = DocumentRecipient::query()
                ->whereKey($ids->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /** @var ReceivingBox $lockedBox */
            $lockedBox = ReceivingBox::query()
                ->whereKey($box)
                ->lockForUpdate()
                ->firstOrFail();

            if ($recipients->count() !== $ids->count()
                || $lockedBox->status !== OperationalStatus::Active
                || $lockedBox->organizational_unit_id !== $actor->organizational_unit_id
                || $lockedBox->organizationalUnit()->where('status', OperationalStatus::Active->value)->doesntExist()) {
                throw $this->invalidSelection();
            }

            foreach ($recipients as $recipient) {
                $document = $documents->get($recipient->document_id);

                if (! $document instanceof Document
                    || $recipient->recipient_unit_id !== $actor->organizational_unit_id
                    || $recipient->receiving_box_id !== $lockedBox->getKey()
                    || $recipient->recipient_status !== RecipientStatus::ReadyForPickup
                    || $recipient->date_placed === null
                    || ! in_array($document->current_status, [
                        DocumentStatus::ReadyForPickup,
                        DocumentStatus::PartiallyClaimed,
                    ], true)) {
                    throw $this->invalidSelection();
                }
            }

            $claimedAt = now();
            $transactions = collect();

            foreach ($recipients as $recipient) {
                /** @var Document $document */
                $document = $documents->get($recipient->document_id);
                $previousStatus = $document->current_status;
                $previousLocation = $document->current_location;

                $recipient->applyClaimState(
                    $actor,
                    $receiverName,
                    $receiverPosition,
                    $claimedAt,
                );
                $recipient->save();

                $hasIncompleteRecipients = DocumentRecipient::query()
                    ->where('document_id', $document->getKey())
                    ->where('recipient_status', '!=', RecipientStatus::ReceivedByRecipientUnit->value)
                    ->exists();
                $newStatus = $hasIncompleteRecipients
                    ? DocumentStatus::PartiallyClaimed
                    : DocumentStatus::Completed;
                $newDocumentLocation = $hasIncompleteRecipients
                    ? PhysicalLocation::ReceivingBox
                    : PhysicalLocation::RecipientUnit;

                $document->applyRoutingState($newStatus, $newDocumentLocation);
                $document->save();

                $transactions->push(DocumentTransaction::query()->create([
                    'document_id' => $document->getKey(),
                    'recipient_id' => $recipient->getKey(),
                    'action' => RoutingAction::ClaimByRecipientUnit,
                    'previous_status' => $previousStatus,
                    'new_status' => $newStatus,
                    'from_location' => $previousLocation,
                    'to_location' => PhysicalLocation::RecipientUnit,
                    'performed_by' => $actor->getKey(),
                    'transaction_date' => $claimedAt,
                    'remarks' => $remarks,
                    'receiver_name' => $receiverName,
                    'receiver_position' => $receiverPosition,
                    'ip_address' => $context['ip_address'] ?? null,
                    'device_info' => $context['device_info'] ?? null,
                ]));
            }

            return $transactions;
        });

        $transactions->each(fn (DocumentTransaction $transaction): int => $this->notifications
            ->dispatchForTransaction($transaction));

        return $transactions;
    }

    private function invalidSelection(): ValidationException
    {
        return ValidationException::withMessages([
            'recipient_ids' => 'One or more selected documents are no longer eligible for confirmation.',
        ]);
    }
}
