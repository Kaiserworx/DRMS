<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\RecipientStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\ReceivingBox;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardMetricsService
{
    /**
     * @return array<string, int>
     */
    public function metricsFor(User $user): array
    {
        return $user->isLevelTwo()
            ? $this->levelTwoMetrics()
            : $this->levelOneMetrics($user);
    }

    /**
     * @return Collection<int, array{unit: string, location: string, waiting: int, oldest_waiting_at: ?Carbon}>
     */
    public function receivingBoxSummary(User $user): Collection
    {
        if (! $user->isLevelTwo()) {
            return collect();
        }

        return ReceivingBox::query()
            ->with('organizationalUnit')
            ->withCount([
                'recipients as waiting_count' => fn (Builder $query) => $query
                    ->where('recipient_status', RecipientStatus::ReadyForPickup->value)
                    ->whereHas('document', fn (Builder $query) => $query
                        ->where('current_status', '!=', DocumentStatus::Cancelled->value)),
            ])
            ->withMin([
                'recipients as oldest_waiting_at' => fn (Builder $query) => $query
                    ->where('recipient_status', RecipientStatus::ReadyForPickup->value)
                    ->whereHas('document', fn (Builder $query) => $query
                        ->where('current_status', '!=', DocumentStatus::Cancelled->value)),
            ], 'date_placed')
            ->orderByDesc('waiting_count')
            ->orderBy('id')
            ->get()
            ->map(fn (ReceivingBox $box): array => [
                'unit' => $box->organizationalUnit->unit_name,
                'location' => $box->box_location ?? 'Not specified',
                'waiting' => (int) $box->waiting_count,
                'oldest_waiting_at' => $box->oldest_waiting_at
                    ? Carbon::parse($box->oldest_waiting_at)
                    : null,
            ]);
    }

    /**
     * @return array<string, int>
     */
    private function levelOneMetrics(User $user): array
    {
        $visible = fn (): Builder => Document::query()->visibleTo($user);
        $ownRecipients = fn (): Builder => DocumentRecipient::query()
            ->where('recipient_unit_id', $user->organizational_unit_id)
            ->whereHas('document', fn (Builder $query) => $query->visibleTo($user));

        return [
            'draft' => $visible()->where('current_status', DocumentStatus::Draft->value)->count(),
            'submitted' => $visible()->where('current_status', DocumentStatus::Submitted->value)->count(),
            'received_managing' => $visible()->where(
                'current_status',
                DocumentStatus::ReceivedAtManagingOffice->value,
            )->count(),
            'forwarded_upstream' => $visible()->where(
                'current_status',
                DocumentStatus::ForwardedToUpstreamOffice->value,
            )->count(),
            'ready_pickup' => $ownRecipients()
                ->where('recipient_status', RecipientStatus::ReadyForPickup->value)
                ->distinct('document_id')
                ->count('document_id'),
            'claimed_today' => $ownRecipients()
                ->whereDate('date_claimed', Carbon::today())
                ->distinct('document_id')
                ->count('document_id'),
            'completed' => $visible()->where('current_status', DocumentStatus::Completed->value)->count(),
            'unread_notifications' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function levelTwoMetrics(): array
    {
        $readyRecipients = fn (): Builder => DocumentRecipient::query()
            ->where('recipient_status', RecipientStatus::ReadyForPickup->value)
            ->whereHas('document', fn (Builder $query) => $query
                ->where('current_status', '!=', DocumentStatus::Cancelled->value));

        $overdue = Document::query()
            ->whereNotIn('current_status', [
                DocumentStatus::Completed->value,
                DocumentStatus::Cancelled->value,
            ])
            ->whereDate('due_date', '<', Carbon::today())
            ->count();

        return [
            'received_today' => Document::query()->whereDate('date_received', Carbon::today())->count(),
            'awaiting_verification' => Document::query()
                ->where('current_status', DocumentStatus::Submitted->value)
                ->count(),
            'waiting_forwarding' => Document::query()
                ->where('current_status', DocumentStatus::ReceivedAtManagingOffice->value)
                ->count(),
            'at_upstream' => Document::query()
                ->whereIn('current_status', [
                    DocumentStatus::ForwardedToUpstreamOffice->value,
                    DocumentStatus::ReceivedAtUpstreamOffice->value,
                ])
                ->count(),
            'returned_upstream' => Document::query()
                ->where('current_status', DocumentStatus::ReturnedFromUpstreamOffice->value)
                ->count(),
            'waiting_distribution' => Document::query()
                ->where('current_status', DocumentStatus::ForDistribution->value)
                ->count(),
            'in_receiving_boxes' => $readyRecipients()->distinct('document_id')->count('document_id'),
            'pending_confirmation' => $readyRecipients()->count(),
            'completed' => Document::query()->where('current_status', DocumentStatus::Completed->value)->count(),
            'overdue_unclaimed' => $overdue + $readyRecipients()
                ->where(function (Builder $query): void {
                    $query->whereNull('documents.due_date')
                        ->orWhereDate('documents.due_date', '>=', Carbon::today());
                })
                ->join('documents', 'documents.id', '=', 'document_recipients.document_id')
                ->count(),
        ];
    }
}
