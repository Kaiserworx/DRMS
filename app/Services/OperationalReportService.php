<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentTransaction;
use App\Models\User;
use Closure;
use Generator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperationalReportService
{
    /**
     * @return array<string, string>
     */
    public function reportOptions(): array
    {
        return [
            'documents_by_date' => 'Documents by date range',
            'documents_by_unit' => 'Documents by organizational unit',
            'documents_by_type' => 'Documents by type',
            'documents_by_origin' => 'Documents by origin',
            'upstream_movements' => 'Upstream Office forwarded and returned',
            'box_inventory' => 'Current receiving-box inventory',
            'unclaimed_documents' => 'Unclaimed documents',
            'claimed_documents' => 'Claimed documents',
            'cancelled_documents' => 'Cancelled documents',
            'transactions_by_user' => 'Transactions by user',
            'monthly_movement' => 'Monthly movement summary',
            'time_averages' => 'Average processing and pickup time',
        ];
    }

    /**
     * @return array{
     *     units: array<int, string>,
     *     types: array<int, string>,
     *     origins: array<int, string>,
     *     users: array<int, string>
     * }
     */
    public function filterOptions(User $user): array
    {
        $search = app(DocumentSearchService::class);

        return [
            'units' => $search->organizationalUnitOptions($user),
            'types' => $search->documentTypeOptions($user),
            'origins' => $search->originOptions($user),
            'users' => User::query()
                ->whereIn('id', DocumentTransaction::query()
                    ->whereHas('document', fn (Builder $query) => $query->visibleTo($user))
                    ->select('performed_by'))
                ->orderBy('full_name')
                ->pluck('full_name', 'id')
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: array<string, string>, rows: LengthAwarePaginator}
     */
    public function paginate(User $user, string $report, array $filters, int $perPage = 25): array
    {
        $filters = $this->validatedFilters($user, $report, $filters);

        if ($report === 'time_averages') {
            $rows = $this->timeAverageRows($user, $filters);

            return [
                'columns' => $this->timeAverageColumns(),
                'rows' => new ConcretePaginator(
                    $rows,
                    $rows->count(),
                    $perPage,
                    1,
                    ['path' => request()->url()],
                ),
            ];
        }

        $dataset = $this->dataset($user, $report, $filters);
        $paginator = $dataset['query']->paginate($perPage)->withQueryString();
        $paginator->through($dataset['transform']);

        return [
            'columns' => $dataset['columns'],
            'rows' => $paginator,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: array<string, string>, rows: Generator<int, array<string, scalar|null>>}
     */
    public function export(User $user, string $report, array $filters): array
    {
        $filters = $this->validatedFilters($user, $report, $filters);

        if ($report === 'time_averages') {
            return [
                'columns' => $this->timeAverageColumns(),
                'rows' => $this->yieldCollection($this->timeAverageRows($user, $filters)),
            ];
        }

        $dataset = $this->dataset($user, $report, $filters);

        return [
            'columns' => $dataset['columns'],
            'rows' => $this->streamDataset($dataset),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function validatedFilters(User $user, string $report, array $filters): array
    {
        $validator = Validator::make(
            ['report' => $report, ...$filters],
            [
                'report' => ['required', Rule::in(array_keys($this->reportOptions()))],
                'from' => ['nullable', 'date'],
                'to' => ['nullable', 'date', 'after_or_equal:from'],
                'organizational_unit_id' => ['nullable', 'integer'],
                'document_type_id' => ['nullable', 'integer'],
                'origin_id' => ['nullable', 'integer'],
                'performed_by' => ['nullable', 'integer'],
            ],
        );

        $validated = $validator->validate();
        unset($validated['report']);

        $options = $this->filterOptions($user);
        $this->validateScopedOption(
            $validated,
            'organizational_unit_id',
            $options['units'],
            'The selected organizational unit is outside your report scope.',
        );
        $this->validateScopedOption(
            $validated,
            'document_type_id',
            $options['types'],
            'The selected document type is outside your report scope.',
        );
        $this->validateScopedOption(
            $validated,
            'origin_id',
            $options['origins'],
            'The selected origin is outside your report scope.',
        );
        $this->validateScopedOption(
            $validated,
            'performed_by',
            $options['users'],
            'The selected user is outside your report scope.',
        );

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     columns: array<string, string>,
     *     query: Builder<Model>|QueryBuilder,
     *     transform: Closure,
     *     stream_by_id: bool
     * }
     */
    private function dataset(User $user, string $report, array $filters): array
    {
        return match ($report) {
            'documents_by_date',
            'documents_by_unit',
            'documents_by_type',
            'documents_by_origin',
            'cancelled_documents' => $this->documentDataset($user, $report, $filters),
            'upstream_movements',
            'transactions_by_user' => $this->transactionDataset($user, $report, $filters),
            'box_inventory',
            'unclaimed_documents',
            'claimed_documents' => $this->recipientDataset($user, $report, $filters),
            'monthly_movement' => $this->monthlyMovementDataset($user, $filters),
            default => throw ValidationException::withMessages(['report' => 'Unsupported report.']),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function documentDataset(User $user, string $report, array $filters): array
    {
        $query = Document::query()
            ->visibleTo($user)
            ->with(['documentType', 'submittingUnit', 'origin'])
            ->when($report === 'cancelled_documents', fn (Builder $query) => $query
                ->where('current_status', DocumentStatus::Cancelled->value));

        $this->applyDocumentFilters($query, $filters, 'documents.created_at');

        return [
            'columns' => [
                'tracking_no' => 'Tracking number',
                'subject' => 'Subject',
                'document_type' => 'Document type',
                'organizational_unit' => 'Organizational unit',
                'origin' => 'Origin',
                'status' => 'Status',
                'location' => 'Current location',
                'created_at' => 'Created',
                'date_received' => 'Received',
                'due_date' => 'Due',
            ],
            'query' => $query->orderByDesc('documents.created_at')->orderByDesc('documents.id'),
            'transform' => fn (Document $document): array => [
                'tracking_no' => $document->tracking_no,
                'subject' => $document->subject,
                'document_type' => $document->documentType->name,
                'organizational_unit' => $document->submittingUnit?->unit_name ?? 'Managing Office',
                'origin' => $document->origin?->origin_name ?? 'Not classified',
                'status' => $document->current_status->label(),
                'location' => $document->current_location->label(),
                'created_at' => $document->created_at->format('Y-m-d H:i'),
                'date_received' => $document->date_received?->format('Y-m-d H:i'),
                'due_date' => $document->due_date?->format('Y-m-d'),
            ],
            'stream_by_id' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function transactionDataset(User $user, string $report, array $filters): array
    {
        $query = DocumentTransaction::query()
            ->whereHas('document', fn (Builder $query) => $query->visibleTo($user))
            ->with(['document', 'performer'])
            ->when($report === 'upstream_movements', fn (Builder $query) => $query
                ->whereIn('action', [
                    RoutingAction::ForwardToUpstreamOffice->value,
                    RoutingAction::ReturnFromUpstreamOffice->value,
                ]));

        $this->applyTransactionFilters($query, $filters);

        return [
            'columns' => [
                'tracking_no' => 'Tracking number',
                'subject' => 'Subject',
                'action' => 'Movement',
                'from' => 'From',
                'to' => 'To',
                'performed_by' => 'Performed by',
                'transaction_date' => 'Transaction date',
                'remarks' => 'Remarks',
            ],
            'query' => $query->orderByDesc('transaction_date')->orderByDesc('id'),
            'transform' => fn (DocumentTransaction $transaction): array => [
                'tracking_no' => $transaction->document->tracking_no,
                'subject' => $transaction->document->subject,
                'action' => $transaction->action->label(),
                'from' => $transaction->from_location->label(),
                'to' => $transaction->to_location->label(),
                'performed_by' => $transaction->performer->full_name,
                'transaction_date' => $transaction->transaction_date->format('Y-m-d H:i'),
                'remarks' => $transaction->remarks,
            ],
            'stream_by_id' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function recipientDataset(User $user, string $report, array $filters): array
    {
        $query = DocumentRecipient::query()
            ->visibleTo($user)
            ->whereHas('document', fn (Builder $query) => $query
                ->visibleTo($user)
                ->where('current_status', '!=', DocumentStatus::Cancelled->value))
            ->with(['document.documentType', 'recipientUnit', 'receivingBox'])
            ->when(in_array($report, ['box_inventory', 'unclaimed_documents'], true), fn (Builder $query) => $query
                ->where('recipient_status', RecipientStatus::ReadyForPickup->value)
                ->whereNotNull('date_placed'))
            ->when($report === 'box_inventory', fn (Builder $query) => $query->whereNotNull('receiving_box_id'))
            ->when($report === 'claimed_documents', fn (Builder $query) => $query
                ->where('recipient_status', RecipientStatus::ReceivedByRecipientUnit->value)
                ->whereNotNull('date_claimed'));

        $this->applyRecipientFilters($query, $filters, $report === 'claimed_documents' ? 'date_claimed' : 'date_placed');

        return [
            'columns' => [
                'tracking_no' => 'Tracking number',
                'subject' => 'Subject',
                'document_type' => 'Document type',
                'recipient_unit' => 'Recipient unit',
                'box_location' => 'Box location',
                'recipient_status' => 'Recipient status',
                'date_placed' => 'Placed',
                'date_claimed' => 'Claimed',
                'receiver' => 'Receiver',
            ],
            'query' => $query->orderByDesc(
                $report === 'claimed_documents' ? 'date_claimed' : 'date_placed',
            )->orderByDesc('id'),
            'transform' => fn (DocumentRecipient $recipient): array => [
                'tracking_no' => $recipient->document->tracking_no,
                'subject' => $recipient->document->subject,
                'document_type' => $recipient->document->documentType->name,
                'recipient_unit' => $recipient->recipientUnit->unit_name,
                'box_location' => $recipient->receivingBox?->box_location,
                'recipient_status' => $recipient->recipient_status->label(),
                'date_placed' => $recipient->date_placed?->format('Y-m-d H:i'),
                'date_claimed' => $recipient->date_claimed?->format('Y-m-d H:i'),
                'receiver' => filled($recipient->received_by_name)
                    ? trim($recipient->received_by_name.' — '.$recipient->received_by_position)
                    : null,
            ],
            'stream_by_id' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function monthlyMovementDataset(User $user, array $filters): array
    {
        $query = DocumentTransaction::query()
            ->selectRaw($this->monthExpression().' as movement_month')
            ->selectRaw('action, COUNT(*) as movement_count')
            ->whereHas('document', fn (Builder $query) => $query->visibleTo($user));

        $this->applyTransactionFilters($query, $filters);

        $query->groupBy('movement_month', 'action')
            ->orderByDesc('movement_month')
            ->orderBy('action');

        return [
            'columns' => [
                'month' => 'Month',
                'movement' => 'Movement',
                'count' => 'Count',
            ],
            'query' => $query,
            'transform' => fn (object $row): array => [
                'month' => Carbon::createFromFormat('Y-m', $row->movement_month)->format('F Y'),
                'movement' => ($row->action instanceof RoutingAction
                    ? $row->action
                    : RoutingAction::from($row->action))->label(),
                'count' => (int) $row->movement_count,
            ],
            'stream_by_id' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, scalar|null>>
     */
    private function timeAverageRows(User $user, array $filters): Collection
    {
        $visibleDocuments = Document::query()->visibleTo($user);
        $this->applyDocumentFilters($visibleDocuments, $filters, 'documents.created_at');

        $submitted = DocumentTransaction::query()
            ->selectRaw('document_id, MIN(transaction_date) as submitted_at')
            ->where('action', RoutingAction::SubmitByUnit->value)
            ->whereIn('document_id', (clone $visibleDocuments)->select('documents.id'))
            ->groupBy('document_id');

        $completed = DocumentTransaction::query()
            ->selectRaw('document_id, MAX(transaction_date) as completed_at')
            ->where('new_status', DocumentStatus::Completed->value)
            ->whereIn('document_id', (clone $visibleDocuments)->select('documents.id'))
            ->groupBy('document_id');

        $processing = DB::query()
            ->fromSub($submitted, 'submitted')
            ->joinSub($completed, 'completed', 'completed.document_id', '=', 'submitted.document_id')
            ->selectRaw('COUNT(*) as observations')
            ->selectRaw('AVG('.$this->secondsDifferenceExpression('submitted_at', 'completed_at').') as average_seconds')
            ->first();

        $pickup = DocumentRecipient::query()
            ->visibleTo($user)
            ->whereIn('document_id', (clone $visibleDocuments)->select('documents.id'))
            ->whereNotNull('date_placed')
            ->whereNotNull('date_claimed')
            ->selectRaw('COUNT(*) as observations')
            ->selectRaw('AVG('.$this->secondsDifferenceExpression('date_placed', 'date_claimed').') as average_seconds')
            ->first();

        return collect([
            [
                'metric' => 'Average processing time',
                'definition' => 'First unit submission to the transaction that completes the document.',
                'observations' => (int) ($processing->observations ?? 0),
                'average_hours' => $this->formatHours($processing->average_seconds ?? null),
            ],
            [
                'metric' => 'Average pickup time',
                'definition' => 'Recipient placement in a receiving box to confirmed recipient claim.',
                'observations' => (int) ($pickup->observations ?? 0),
                'average_hours' => $this->formatHours($pickup->average_seconds ?? null),
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function timeAverageColumns(): array
    {
        return [
            'metric' => 'Metric',
            'definition' => 'Definition',
            'observations' => 'Completed observations',
            'average_hours' => 'Average hours',
        ];
    }

    /**
     * @param  Builder<Document>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDocumentFilters(Builder $query, array $filters, string $dateColumn): void
    {
        $query
            ->when(filled($filters['from'] ?? null), fn (Builder $query) => $query
                ->whereDate($dateColumn, '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $query) => $query
                ->whereDate($dateColumn, '<=', $filters['to']))
            ->when(filled($filters['organizational_unit_id'] ?? null), function (Builder $query) use ($filters): void {
                $unitId = (int) $filters['organizational_unit_id'];
                $query->where(function (Builder $query) use ($unitId): void {
                    $query->where('submitting_unit_id', $unitId)
                        ->orWhereHas('creator', fn (Builder $query) => $query
                            ->where('organizational_unit_id', $unitId))
                        ->orWhereHas('recipients', fn (Builder $query) => $query
                            ->where('recipient_unit_id', $unitId));
                });
            })
            ->when(filled($filters['document_type_id'] ?? null), fn (Builder $query) => $query
                ->where('document_type_id', (int) $filters['document_type_id']))
            ->when(filled($filters['origin_id'] ?? null), fn (Builder $query) => $query
                ->where('origin_id', (int) $filters['origin_id']));
    }

    /**
     * @param  Builder<DocumentTransaction>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyTransactionFilters(Builder $query, array $filters): void
    {
        $query
            ->when(filled($filters['from'] ?? null), fn (Builder $query) => $query
                ->whereDate('transaction_date', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $query) => $query
                ->whereDate('transaction_date', '<=', $filters['to']))
            ->when(filled($filters['performed_by'] ?? null), fn (Builder $query) => $query
                ->where('performed_by', (int) $filters['performed_by']))
            ->when($this->hasDocumentFilters($filters), fn (Builder $query) => $query
                ->whereHas('document', function (Builder $query) use ($filters): void {
                    $this->applyDocumentFilters($query, $filters, 'documents.created_at');
                }));
    }

    /**
     * @param  Builder<DocumentRecipient>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyRecipientFilters(Builder $query, array $filters, string $dateColumn): void
    {
        $query
            ->when(filled($filters['from'] ?? null), fn (Builder $query) => $query
                ->whereDate($dateColumn, '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $query) => $query
                ->whereDate($dateColumn, '<=', $filters['to']))
            ->when(filled($filters['organizational_unit_id'] ?? null), fn (Builder $query) => $query
                ->where('recipient_unit_id', (int) $filters['organizational_unit_id']))
            ->when(
                filled($filters['document_type_id'] ?? null) || filled($filters['origin_id'] ?? null),
                fn (Builder $query) => $query->whereHas('document', function (Builder $query) use ($filters): void {
                    $this->applyDocumentFilters($query, $filters, 'documents.created_at');
                }),
            );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasDocumentFilters(array $filters): bool
    {
        return filled($filters['organizational_unit_id'] ?? null)
            || filled($filters['document_type_id'] ?? null)
            || filled($filters['origin_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $options
     */
    private function validateScopedOption(
        array $filters,
        string $key,
        array $options,
        string $message,
    ): void {
        if (blank($filters[$key] ?? null)) {
            return;
        }

        if (! array_key_exists((int) $filters[$key], $options)) {
            throw ValidationException::withMessages([$key => $message]);
        }
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return Generator<int, array<string, scalar|null>>
     */
    private function streamDataset(array $dataset): Generator
    {
        $rows = $dataset['stream_by_id']
            ? $dataset['query']->reorder()->lazyById(500)
            : $dataset['query']->cursor();

        foreach ($rows as $row) {
            yield ($dataset['transform'])($row);
        }
    }

    /**
     * @param  Collection<int, array<string, scalar|null>>  $rows
     * @return Generator<int, array<string, scalar|null>>
     */
    private function yieldCollection(Collection $rows): Generator
    {
        foreach ($rows as $row) {
            yield $row;
        }
    }

    private function formatHours(mixed $seconds): ?string
    {
        return $seconds === null ? null : number_format(((float) $seconds) / 3600, 2, '.', '');
    }

    private function monthExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', transaction_date)"
            : "DATE_FORMAT(transaction_date, '%Y-%m')";
    }

    private function secondsDifferenceExpression(string $start, string $end): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(julianday({$end}) - julianday({$start})) * 86400"
            : "TIMESTAMPDIFF(SECOND, {$start}, {$end})";
    }
}
