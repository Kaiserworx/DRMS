<?php

namespace App\Services;

use App\Enums\OperationalStatus;
use App\Enums\ReceivingBoxTokenAction;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\ReceivingBoxTokenAudit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ReceivingBoxService
{
    /**
     * @param  array{organizational_unit_id: int|string, box_location?: ?string, status?: string|OperationalStatus}  $data
     * @param  array{ip_address?: ?string, device_info?: ?string}  $context
     */
    public function create(User $actor, array $data, array $context = []): ReceivingBox
    {
        if (! $actor->isLevelTwo()) {
            throw new AuthorizationException('Only Level 2 users may create receiving boxes.');
        }

        $unitId = (int) $data['organizational_unit_id'];
        $requestedStatus = $data['status'] ?? OperationalStatus::Active;
        $status = $requestedStatus instanceof OperationalStatus
            ? $requestedStatus
            : OperationalStatus::from($requestedStatus);

        if (ReceivingBox::query()->where('organizational_unit_id', $unitId)->exists()) {
            throw ValidationException::withMessages([
                'organizational_unit_id' => 'This organizational unit already has a receiving box.',
            ]);
        }

        if ($status === OperationalStatus::Active && ! OrganizationalUnit::query()
            ->whereKey($unitId)
            ->where('status', OperationalStatus::Active->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'organizational_unit_id' => 'An active receiving box requires an active organizational unit.',
            ]);
        }

        return DB::transaction(function () use ($actor, $data, $context, $unitId, $status): ReceivingBox {
            $token = $this->uniqueToken();
            $generatedAt = now();

            $box = new ReceivingBox([
                'organizational_unit_id' => $unitId,
                'box_location' => filled($data['box_location'] ?? null)
                    ? trim((string) $data['box_location'])
                    : null,
                'status' => $status,
            ]);
            $box->forceFill([
                'qr_token' => $token,
                'last_qr_generated_at' => $generatedAt,
            ])->save();

            $this->recordTokenAudit(
                actor: $actor,
                box: $box,
                action: ReceivingBoxTokenAction::Generated,
                previousToken: null,
                newToken: $token,
                context: $context,
                occurredAt: $generatedAt,
            );

            return $box;
        });
    }

    /**
     * @param  array{ip_address?: ?string, device_info?: ?string}  $context
     */
    public function regenerateToken(User $actor, ReceivingBox $box, array $context = []): ReceivingBox
    {
        if (! $actor->isLevelTwo()) {
            throw new AuthorizationException('Only Level 2 users may regenerate receiving-box tokens.');
        }

        return DB::transaction(function () use ($actor, $box, $context): ReceivingBox {
            /** @var ReceivingBox $locked */
            $locked = ReceivingBox::query()->whereKey($box)->lockForUpdate()->firstOrFail();
            $previousToken = $locked->qr_token;
            $newToken = $this->uniqueToken();
            $generatedAt = now();

            $locked->forceFill([
                'qr_token' => $newToken,
                'last_qr_generated_at' => $generatedAt,
            ])->save();

            $this->recordTokenAudit(
                actor: $actor,
                box: $locked,
                action: ReceivingBoxTokenAction::Regenerated,
                previousToken: $previousToken,
                newToken: $newToken,
                context: $context,
                occurredAt: $generatedAt,
            );

            return $locked;
        });
    }

    private function uniqueToken(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = Str::random(64);

            if (! ReceivingBox::query()->where('qr_token', $token)->exists()) {
                return $token;
            }
        }

        throw new RuntimeException('Unable to generate a unique receiving-box token.');
    }

    /**
     * @param  array{ip_address?: ?string, device_info?: ?string}  $context
     */
    private function recordTokenAudit(
        User $actor,
        ReceivingBox $box,
        ReceivingBoxTokenAction $action,
        ?string $previousToken,
        string $newToken,
        array $context,
        mixed $occurredAt,
    ): void {
        ReceivingBoxTokenAudit::query()->create([
            'receiving_box_id' => $box->getKey(),
            'action' => $action,
            'previous_token_fingerprint' => $previousToken === null ? null : hash('sha256', $previousToken),
            'new_token_fingerprint' => hash('sha256', $newToken),
            'performed_by' => $actor->getKey(),
            'occurred_at' => $occurredAt,
            'ip_address' => $context['ip_address'] ?? null,
            'device_info' => $context['device_info'] ?? null,
        ]);
    }
}
