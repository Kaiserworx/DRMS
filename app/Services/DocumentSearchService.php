<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\OriginType;
use App\Enums\PhysicalLocation;
use App\Enums\Priority;
use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use App\Models\Document;
use App\Models\DocumentOrigin;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DocumentSearchService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Document>
     */
    public function queryFor(User $user, array $filters = []): Builder
    {
        $query = Document::query()
            ->visibleTo($user)
            ->with(['documentType', 'submittingUnit', 'origin']);

        if (filled($filters['search'] ?? null)) {
            $this->applyTerm($query, (string) $filters['search']);
        }

        return $this->applyFilters($query, $filters);
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function applyTerm(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $like = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';
        $documentStatuses = $this->matchingValues(DocumentStatus::cases(), $search);
        $recipientStatuses = $this->matchingValues(RecipientStatus::cases(), $search);
        $locations = $this->matchingValues(PhysicalLocation::cases(), $search);
        $priorities = $this->matchingValues(Priority::cases(), $search);
        $originTypes = $this->matchingValues(OriginType::cases(), $search);

        return $query->where(function (Builder $query) use (
            $like,
            $documentStatuses,
            $recipientStatuses,
            $locations,
            $priorities,
            $originTypes,
        ): void {
            $query
                ->whereRaw('LOWER(documents.tracking_no) LIKE ?', [$like])
                ->orWhereRaw('LOWER(documents.subject) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(documents.description, ?)) LIKE ?', ['', $like])
                ->orWhereRaw('LOWER(COALESCE(documents.remarks, ?)) LIKE ?', ['', $like])
                ->orWhereRaw('LOWER(COALESCE(documents.origin_reference_no, ?)) LIKE ?', ['', $like])
                ->orWhereHas('documentType', fn (Builder $query) => $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like]))
                ->orWhereHas('origin', function (Builder $query) use ($like, $originTypes): void {
                    $query->whereRaw('LOWER(origin_name) LIKE ?', [$like])
                        ->when($originTypes !== [], fn (Builder $query) => $query
                            ->orWhereIn('origin_type', $originTypes));
                })
                ->orWhereHas('submittingUnit', fn (Builder $query) => $this->unitTerm($query, $like))
                ->orWhereHas('creator.organizationalUnit', fn (Builder $query) => $this->unitTerm($query, $like))
                ->orWhereHas('recipients', function (Builder $query) use ($like, $recipientStatuses): void {
                    $query->whereRaw('LOWER(COALESCE(remarks, ?)) LIKE ?', ['', $like])
                        ->orWhereRaw('LOWER(COALESCE(received_by_name, ?)) LIKE ?', ['', $like])
                        ->orWhereRaw('LOWER(COALESCE(received_by_position, ?)) LIKE ?', ['', $like])
                        ->orWhereHas('recipientUnit', fn (Builder $query) => $this->unitTerm($query, $like))
                        ->when($recipientStatuses !== [], fn (Builder $query) => $query
                            ->orWhereIn('recipient_status', $recipientStatuses));
                })
                ->orWhereHas('transactions', function (Builder $query) use ($like): void {
                    $query->whereRaw('LOWER(COALESCE(remarks, ?)) LIKE ?', ['', $like])
                        ->orWhereRaw('LOWER(COALESCE(receiver_name, ?)) LIKE ?', ['', $like])
                        ->orWhereRaw('LOWER(COALESCE(receiver_position, ?)) LIKE ?', ['', $like]);
                })
                ->when($documentStatuses !== [], fn (Builder $query) => $query
                    ->orWhereIn('current_status', $documentStatuses))
                ->when($locations !== [], fn (Builder $query) => $query
                    ->orWhereIn('current_location', $locations))
                ->when($priorities !== [], fn (Builder $query) => $query
                    ->orWhereIn('priority', $priorities));
        });
    }

    /**
     * @param  Builder<Document>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Document>
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        $this->validateDateRanges($filters);

        $query
            ->when(filled($filters['document_type_id'] ?? null), fn (Builder $query) => $query
                ->where('document_type_id', (int) $filters['document_type_id']))
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
            ->when(filled($filters['origin_type'] ?? null), fn (Builder $query) => $query
                ->whereHas('origin', fn (Builder $query) => $query
                    ->where('origin_type', $filters['origin_type'])))
            ->when(filled($filters['origin_id'] ?? null), fn (Builder $query) => $query
                ->where('origin_id', (int) $filters['origin_id']))
            ->when(filled($filters['destination_unit_id'] ?? null), fn (Builder $query) => $query
                ->whereHas('recipients', fn (Builder $query) => $query
                    ->where('recipient_unit_id', (int) $filters['destination_unit_id'])))
            ->when(filled($filters['current_status'] ?? null), fn (Builder $query) => $query
                ->where('current_status', $filters['current_status']))
            ->when(filled($filters['recipient_status'] ?? null), fn (Builder $query) => $query
                ->whereHas('recipients', fn (Builder $query) => $query
                    ->where('recipient_status', $filters['recipient_status'])))
            ->when(filled($filters['current_location'] ?? null), fn (Builder $query) => $query
                ->where('current_location', $filters['current_location']))
            ->when(filled($filters['priority'] ?? null), fn (Builder $query) => $query
                ->where('priority', $filters['priority']));

        $this->applyDocumentDateRange($query, 'created_at', $filters, 'created');
        $this->applyDocumentDateRange($query, 'date_received', $filters, 'received');
        $this->applyTransactionDateRange($query, RoutingAction::SubmitByUnit, $filters, 'submitted');
        $this->applyTransactionDateRange($query, RoutingAction::ForwardToUpstreamOffice, $filters, 'forwarded');
        $this->applyRecipientDateRange($query, 'date_placed', $filters, 'placed');
        $this->applyRecipientDateRange($query, 'date_claimed', $filters, 'claimed');

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public function documentTypeOptions(User $user): array
    {
        return DocumentType::query()
            ->whereIn('id', $this->visibleDocuments($user)->select('document_type_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function originOptions(User $user): array
    {
        return DocumentOrigin::query()
            ->whereIn('id', $this->visibleDocuments($user)->whereNotNull('origin_id')->select('origin_id'))
            ->orderBy('origin_name')
            ->pluck('origin_name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function organizationalUnitOptions(User $user): array
    {
        $visibleIds = $this->visibleDocuments($user)->select('id');
        $submittingIds = $this->visibleDocuments($user)->whereNotNull('submitting_unit_id')->select('submitting_unit_id');
        $creatorIds = $this->visibleDocuments($user)->select('created_by');

        return OrganizationalUnit::query()
            ->where(function (Builder $query) use ($visibleIds, $submittingIds, $creatorIds): void {
                $query->whereIn('id', $submittingIds)
                    ->orWhereIn('id', DocumentRecipient::query()
                        ->whereIn('document_id', $visibleIds)
                        ->select('recipient_unit_id'))
                    ->orWhereIn('id', User::query()
                        ->whereIn('id', $creatorIds)
                        ->whereNotNull('organizational_unit_id')
                        ->select('organizational_unit_id'));
            })
            ->orderBy('unit_name')
            ->pluck('unit_name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function destinationOptions(User $user): array
    {
        return OrganizationalUnit::query()
            ->whereIn('id', DocumentRecipient::query()
                ->whereIn('document_id', $this->visibleDocuments($user)->select('id'))
                ->select('recipient_unit_id'))
            ->orderBy('unit_name')
            ->pluck('unit_name', 'id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function validateDateRanges(array $filters): void
    {
        $errors = [];

        foreach (['created', 'submitted', 'received', 'forwarded', 'placed', 'claimed'] as $name) {
            $from = $filters["{$name}_from"] ?? null;
            $to = $filters["{$name}_to"] ?? null;

            if (filled($from) && filled($to) && Carbon::parse($from)->startOfDay()->gt(Carbon::parse($to)->endOfDay())) {
                $errors["{$name}_to"] = ucfirst($name).' end date must be on or after its start date.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  Builder<Document>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDocumentDateRange(Builder $query, string $column, array $filters, string $name): void
    {
        $query
            ->when(filled($filters["{$name}_from"] ?? null), fn (Builder $query) => $query
                ->whereDate($column, '>=', $filters["{$name}_from"]))
            ->when(filled($filters["{$name}_to"] ?? null), fn (Builder $query) => $query
                ->whereDate($column, '<=', $filters["{$name}_to"]));
    }

    /**
     * @param  Builder<Document>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyTransactionDateRange(
        Builder $query,
        RoutingAction $action,
        array $filters,
        string $name,
    ): void {
        if (blank($filters["{$name}_from"] ?? null) && blank($filters["{$name}_to"] ?? null)) {
            return;
        }

        $query->whereHas('transactions', function (Builder $query) use ($action, $filters, $name): void {
            $query->where('action', $action->value)
                ->when(filled($filters["{$name}_from"] ?? null), fn (Builder $query) => $query
                    ->whereDate('transaction_date', '>=', $filters["{$name}_from"]))
                ->when(filled($filters["{$name}_to"] ?? null), fn (Builder $query) => $query
                    ->whereDate('transaction_date', '<=', $filters["{$name}_to"]));
        });
    }

    /**
     * @param  Builder<Document>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyRecipientDateRange(
        Builder $query,
        string $column,
        array $filters,
        string $name,
    ): void {
        if (blank($filters["{$name}_from"] ?? null) && blank($filters["{$name}_to"] ?? null)) {
            return;
        }

        $query->whereHas('recipients', fn (Builder $query) => $query
            ->when(filled($filters["{$name}_from"] ?? null), fn (Builder $query) => $query
                ->whereDate($column, '>=', $filters["{$name}_from"]))
            ->when(filled($filters["{$name}_to"] ?? null), fn (Builder $query) => $query
                ->whereDate($column, '<=', $filters["{$name}_to"])));
    }

    /**
     * @param  array<int, object>  $cases
     * @return array<int, string>
     */
    private function matchingValues(array $cases, string $search): array
    {
        $search = mb_strtolower($search);

        return collect($cases)
            ->filter(fn (object $case): bool => str_contains(mb_strtolower($case->value), $search)
                || str_contains(mb_strtolower($case->label()), $search))
            ->map(fn (object $case): string => $case->value)
            ->values()
            ->all();
    }

    /**
     * @param  Builder<OrganizationalUnit>  $query
     */
    private function unitTerm(Builder $query, string $like): void
    {
        $query->whereRaw('LOWER(unit_name) LIKE ?', [$like])
            ->orWhereRaw('LOWER(unit_code) LIKE ?', [$like])
            ->orWhereRaw('LOWER(COALESCE(short_name, ?)) LIKE ?', ['', $like]);
    }

    /**
     * @return Builder<Document>
     */
    private function visibleDocuments(User $user): Builder
    {
        return Document::query()->visibleTo($user);
    }
}
