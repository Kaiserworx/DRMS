<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\OperationalStatus;
use App\Enums\PhysicalLocation;
use App\Enums\Priority;
use App\Models\Document;
use App\Models\DocumentOrigin;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentRegistrationService
{
    public function __construct(
        private readonly TrackingNumberGenerator $trackingNumbers,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function register(User $actor, array $input): Document
    {
        $validated = Validator::make($input, [
            'document_type_id' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'origin_id' => ['nullable', 'integer'],
            'origin_reference_no' => ['nullable', 'string', 'max:255'],
            'submitting_unit_id' => ['nullable', 'integer'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'initial_status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'date_received' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date_received'],
            'remarks' => ['nullable', 'string'],
        ])->validate();

        $initialStatus = DocumentStatus::from($validated['initial_status'] ?? DocumentStatus::Draft->value);
        if ($initialStatus !== DocumentStatus::Draft) {
            throw ValidationException::withMessages([
                'initial_status' => 'Documents begin as Draft and must be submitted through the routing action.',
            ]);
        }

        $type = DocumentType::query()->find($validated['document_type_id']);
        if (! $type || $type->status !== OperationalStatus::Active) {
            throw ValidationException::withMessages([
                'document_type_id' => 'Select an active document type.',
            ]);
        }

        if ($actor->isLevelTwo()) {
            $unitId = $this->validatedUnitId($validated['submitting_unit_id'] ?? null);
            $originId = $this->validatedOriginId($validated['origin_id'] ?? null);
        } else {
            if (! $actor->organizational_unit_id) {
                throw ValidationException::withMessages([
                    'submitting_unit_id' => 'Your account must belong to an active organizational unit.',
                ]);
            }

            if (filled($validated['origin_id'] ?? null) || filled($validated['origin_reference_no'] ?? null)) {
                throw ValidationException::withMessages([
                    'origin_id' => 'Level 1 users cannot set a document origin.',
                ]);
            }

            $unitId = $this->validatedUnitId($actor->organizational_unit_id);
            $originId = null;
        }

        return DB::transaction(function () use (
            $actor,
            $validated,
            $initialStatus,
            $unitId,
            $originId,
        ): Document {
            $document = new Document;
            $document->fill($validated);
            $document->forceFill([
                'tracking_no' => $this->trackingNumbers->next(),
                'origin_id' => $originId,
                'submitting_unit_id' => $unitId,
                'created_by' => $actor->getKey(),
                'current_status' => $initialStatus,
                'current_location' => $unitId
                    ? PhysicalLocation::OrganizationalUnit
                    : PhysicalLocation::ManagingOffice,
            ]);
            $document->save();

            return $document->refresh();
        });
    }

    private function validatedUnitId(mixed $unitId): ?int
    {
        if (blank($unitId)) {
            return null;
        }

        $unit = OrganizationalUnit::query()->find($unitId);
        if (! $unit || $unit->status !== OperationalStatus::Active) {
            throw ValidationException::withMessages([
                'submitting_unit_id' => 'Select an active organizational unit.',
            ]);
        }

        return $unit->getKey();
    }

    private function validatedOriginId(mixed $originId): ?int
    {
        if (blank($originId)) {
            return null;
        }

        $origin = DocumentOrigin::query()->find($originId);
        if (! $origin || $origin->status !== OperationalStatus::Active) {
            throw ValidationException::withMessages([
                'origin_id' => 'Select an active document origin.',
            ]);
        }

        return $origin->getKey();
    }
}
