<?php

namespace App\Services;

use App\Enums\OperationalStatus;
use App\Enums\RecipientStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecipientAssignmentService
{
    /**
     * @param  array<int, int|string>  $unitIds
     * @return Collection<int, DocumentRecipient>
     */
    public function assign(User $actor, Document $document, array $unitIds, ?string $remarks = null): Collection
    {
        $this->authorizeLevelTwo($actor);

        $normalizedIds = collect($unitIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->values();

        if ($normalizedIds->isEmpty()) {
            throw ValidationException::withMessages([
                'recipient_unit_ids' => 'Select at least one recipient unit.',
            ]);
        }

        if ($normalizedIds->unique()->count() !== $normalizedIds->count()) {
            throw ValidationException::withMessages([
                'recipient_unit_ids' => 'Each recipient unit may only be selected once.',
            ]);
        }

        $activeCount = OrganizationalUnit::query()
            ->whereKey($normalizedIds)
            ->where('status', OperationalStatus::Active->value)
            ->count();

        if ($activeCount !== $normalizedIds->count()) {
            throw ValidationException::withMessages([
                'recipient_unit_ids' => 'Every recipient must be an active organizational unit.',
            ]);
        }

        return DB::transaction(function () use ($document, $normalizedIds, $remarks): Collection {
            Document::query()->whereKey($document)->lockForUpdate()->firstOrFail();

            $existing = DocumentRecipient::query()
                ->where('document_id', $document->getKey())
                ->whereIn('recipient_unit_id', $normalizedIds)
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'recipient_unit_ids' => 'One or more selected units are already assigned.',
                ]);
            }

            return $normalizedIds->map(fn (int $unitId): DocumentRecipient => DocumentRecipient::query()->create([
                'document_id' => $document->getKey(),
                'recipient_unit_id' => $unitId,
                'recipient_status' => RecipientStatus::Assigned,
                'date_assigned' => now(),
                'remarks' => $remarks,
            ]));
        });
    }

    public function remove(User $actor, DocumentRecipient $recipient): void
    {
        $this->authorizeLevelTwo($actor);

        DB::transaction(function () use ($recipient): void {
            /** @var DocumentRecipient $locked */
            $locked = DocumentRecipient::query()->whereKey($recipient)->lockForUpdate()->firstOrFail();

            if ($locked->hasDownstreamActivity()) {
                throw ValidationException::withMessages([
                    'recipient' => 'A recipient assignment cannot be removed after downstream activity.',
                ]);
            }

            $locked->delete();
        });
    }

    private function authorizeLevelTwo(User $actor): void
    {
        if (! $actor->isLevelTwo()) {
            throw new AuthorizationException('Only Level 2 users may manage recipient assignments.');
        }
    }
}
