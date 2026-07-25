<?php

namespace App\Services;

use App\Enums\RecipientStatus;
use App\Models\Document;

class RecipientStatusSummary
{
    /**
     * This read-only summary prepares aggregate reporting without changing the
     * document workflow before the claim phase is implemented.
     *
     * @return array<string, int>
     */
    public function for(Document $document): array
    {
        $counts = $document->recipients()
            ->selectRaw('recipient_status, count(*) as aggregate')
            ->groupBy('recipient_status')
            ->pluck('aggregate', 'recipient_status');

        return collect(RecipientStatus::cases())
            ->mapWithKeys(fn (RecipientStatus $status): array => [
                $status->value => (int) ($counts[$status->value] ?? 0),
            ])
            ->all();
    }
}
