<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\RoutingAction;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentTransaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentRoutingService
{
    public function __construct(
        private readonly RecipientAssignmentService $recipientAssignments,
    ) {}

    /**
     * @param  array{ip_address?: ?string, device_info?: ?string}  $context
     */
    public function perform(
        User $actor,
        Document $document,
        RoutingAction $action,
        ?string $remarks = null,
        array $context = [],
    ): DocumentTransaction {
        if ($action === RoutingAction::AssignRecipientUnit) {
            throw ValidationException::withMessages([
                'action' => 'Use recipient assignment for the Assign Recipient Unit action.',
            ]);
        }

        if ($action === RoutingAction::PlaceInReceivingBox) {
            throw ValidationException::withMessages([
                'action' => 'Receiving-box placement is reserved for Phase 6.',
            ]);
        }

        return DB::transaction(function () use ($actor, $document, $action, $remarks, $context): DocumentTransaction {
            /** @var Document $locked */
            $locked = Document::query()->whereKey($document)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $locked, $action);

            [$newStatus, $newLocation] = $this->transitionFor($locked, $action);

            if ($action === RoutingAction::Cancel && blank($remarks)) {
                throw ValidationException::withMessages([
                    'remarks' => 'A cancellation reason is required.',
                ]);
            }

            $previousStatus = $locked->current_status;
            $previousLocation = $locked->current_location;

            $locked->applyRoutingState($newStatus, $newLocation);
            if ($action === RoutingAction::Cancel) {
                $locked->forceFill([
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor->getKey(),
                    'cancellation_reason' => trim((string) $remarks),
                ]);
            }
            $locked->save();

            return $this->record(
                actor: $actor,
                document: $locked,
                action: $action,
                previousStatus: $previousStatus,
                newStatus: $newStatus,
                fromLocation: $previousLocation,
                toLocation: $newLocation,
                remarks: $remarks,
                context: $context,
            );
        });
    }

    /**
     * @param  array<int, int|string>  $unitIds
     * @param  array{ip_address?: ?string, device_info?: ?string}  $context
     * @return Collection<int, DocumentRecipient>
     */
    public function assignRecipients(
        User $actor,
        Document $document,
        array $unitIds,
        ?string $remarks = null,
        array $context = [],
    ): Collection {
        return DB::transaction(function () use ($actor, $document, $unitIds, $remarks, $context): Collection {
            /** @var Document $locked */
            $locked = Document::query()->whereKey($document)->lockForUpdate()->firstOrFail();

            if (! $actor->isLevelTwo()) {
                throw new AuthorizationException('Only Level 2 users may assign recipient units.');
            }

            if ($locked->current_status !== DocumentStatus::ForDistribution) {
                throw ValidationException::withMessages([
                    'action' => 'Recipient units can only be assigned when the document is For Distribution.',
                ]);
            }

            $recipients = $this->recipientAssignments->assign($actor, $locked, $unitIds, $remarks);

            foreach ($recipients as $recipient) {
                $this->record(
                    actor: $actor,
                    document: $locked,
                    action: RoutingAction::AssignRecipientUnit,
                    previousStatus: $locked->current_status,
                    newStatus: $locked->current_status,
                    fromLocation: $locked->current_location,
                    toLocation: $locked->current_location,
                    remarks: $remarks,
                    context: $context,
                    recipient: $recipient,
                );
            }

            return $recipients;
        });
    }

    /**
     * @return array<int, RoutingAction>
     */
    public function allowedActions(User $actor, Document $document): array
    {
        return collect([
            RoutingAction::SubmitByUnit,
            RoutingAction::ReceiveAtManagingOffice,
            RoutingAction::ForwardToUpstreamOffice,
            RoutingAction::RecordUpstreamReceipt,
            RoutingAction::ReturnFromUpstreamOffice,
            RoutingAction::MarkForDistribution,
            RoutingAction::AssignRecipientUnit,
            RoutingAction::Cancel,
        ])->filter(function (RoutingAction $action) use ($actor, $document): bool {
            try {
                $this->authorize($actor, $document, $action);

                if ($action === RoutingAction::AssignRecipientUnit) {
                    return $document->current_status === DocumentStatus::ForDistribution;
                }

                $this->transitionFor($document, $action);

                return true;
            } catch (AuthorizationException|ValidationException) {
                return false;
            }
        })->values()->all();
    }

    private function authorize(User $actor, Document $document, RoutingAction $action): void
    {
        if ($actor->isLevelTwo()) {
            return;
        }

        if ($action === RoutingAction::SubmitByUnit
            && $document->submitting_unit_id === $actor->organizational_unit_id) {
            return;
        }

        throw new AuthorizationException('You are not authorized to perform this routing action.');
    }

    /**
     * @return array{DocumentStatus, PhysicalLocation}
     */
    private function transitionFor(Document $document, RoutingAction $action): array
    {
        $transition = match ($action) {
            RoutingAction::SubmitByUnit => $document->current_status === DocumentStatus::Draft
                ? [DocumentStatus::Submitted, PhysicalLocation::ManagingOffice]
                : null,
            RoutingAction::ReceiveAtManagingOffice => $document->current_status === DocumentStatus::Submitted
                ? [DocumentStatus::ReceivedAtManagingOffice, PhysicalLocation::ManagingOffice]
                : null,
            RoutingAction::ForwardToUpstreamOffice => $document->current_status === DocumentStatus::ReceivedAtManagingOffice
                ? [DocumentStatus::ForwardedToUpstreamOffice, PhysicalLocation::UpstreamOffice]
                : null,
            RoutingAction::RecordUpstreamReceipt => $document->current_status === DocumentStatus::ForwardedToUpstreamOffice
                ? [DocumentStatus::ReceivedAtUpstreamOffice, PhysicalLocation::UpstreamOffice]
                : null,
            RoutingAction::ReturnFromUpstreamOffice => in_array($document->current_status, [
                DocumentStatus::ForwardedToUpstreamOffice,
                DocumentStatus::ReceivedAtUpstreamOffice,
            ], true)
                ? [DocumentStatus::ReturnedFromUpstreamOffice, PhysicalLocation::ManagingOffice]
                : null,
            RoutingAction::MarkForDistribution => in_array($document->current_status, [
                DocumentStatus::ReceivedAtManagingOffice,
                DocumentStatus::ReturnedFromUpstreamOffice,
            ], true)
                ? [DocumentStatus::ForDistribution, PhysicalLocation::ManagingOffice]
                : null,
            RoutingAction::Cancel => ! in_array($document->current_status, [
                DocumentStatus::Completed,
                DocumentStatus::Cancelled,
            ], true)
                ? [DocumentStatus::Cancelled, $document->current_location]
                : null,
            RoutingAction::AssignRecipientUnit, RoutingAction::PlaceInReceivingBox => null,
        };

        if ($transition === null) {
            throw ValidationException::withMessages([
                'action' => "The {$action->label()} action is not allowed from the document's current state.",
            ]);
        }

        return $transition;
    }

    /**
     * @param  array{ip_address?: ?string, device_info?: ?string}  $context
     */
    private function record(
        User $actor,
        Document $document,
        RoutingAction $action,
        DocumentStatus $previousStatus,
        DocumentStatus $newStatus,
        PhysicalLocation $fromLocation,
        PhysicalLocation $toLocation,
        ?string $remarks,
        array $context,
        ?DocumentRecipient $recipient = null,
    ): DocumentTransaction {
        return DocumentTransaction::query()->create([
            'document_id' => $document->getKey(),
            'recipient_id' => $recipient?->getKey(),
            'action' => $action,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'from_location' => $fromLocation,
            'to_location' => $toLocation,
            'performed_by' => $actor->getKey(),
            'transaction_date' => now(),
            'remarks' => filled($remarks) ? trim($remarks) : null,
            'ip_address' => $context['ip_address'] ?? null,
            'device_info' => $context['device_info'] ?? null,
        ]);
    }
}
