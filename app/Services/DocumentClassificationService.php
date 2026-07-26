<?php

namespace App\Services;

use App\Enums\OperationalStatus;
use App\Models\Document;
use App\Models\DocumentOrigin;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DocumentClassificationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function classifyOrigin(
        User $actor,
        Document $document,
        int $originId,
        ?string $originReferenceNo = null,
    ): Document {
        if (! $actor->isLevelTwo()) {
            throw new AuthorizationException('Only Level 2 users may define an official document origin.');
        }

        $validated = Validator::make(
            [
                'origin_id' => $originId,
                'origin_reference_no' => $originReferenceNo,
            ],
            [
                'origin_id' => ['required', 'integer'],
                'origin_reference_no' => ['nullable', 'string', 'max:255'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $document, $validated): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->getKey());

            if ($locked->origin_id !== null) {
                throw ValidationException::withMessages([
                    'origin_id' => 'This document already has an official origin.',
                ]);
            }

            $origin = DocumentOrigin::query()
                ->whereKey($validated['origin_id'])
                ->where('status', OperationalStatus::Active->value)
                ->first();

            if (! $origin) {
                throw ValidationException::withMessages([
                    'origin_id' => 'Select an active document origin.',
                ]);
            }

            $reference = filled($validated['origin_reference_no'] ?? null)
                ? trim((string) $validated['origin_reference_no'])
                : null;

            $locked->applyOriginClassification($origin->getKey(), $reference);
            $locked->save();

            $this->audit->record(
                'document.origin_classified',
                $locked,
                [
                    'origin_name' => $origin->origin_name,
                    'origin_reference_no' => $reference,
                ],
                $actor,
            );

            return $locked->fresh(['origin']);
        });
    }
}
