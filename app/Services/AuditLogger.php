<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function record(
        string $eventType,
        ?Model $subject = null,
        array $details = [],
        ?User $actor = null,
    ): AuditEvent {
        $actor ??= Auth::user();
        $request = app()->bound('request') ? request() : null;
        $ipAddress = $request?->ip();

        if (! is_string($ipAddress) || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            $ipAddress = null;
        }

        return AuditEvent::query()->create([
            'event_type' => $eventType,
            'actor_id' => $actor?->getKey(),
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'details' => $details === [] ? null : $details,
            'ip_address' => $ipAddress,
            'device_info' => filled($request?->userAgent())
                ? Str::limit((string) $request?->userAgent(), 1000, '')
                : null,
            'occurred_at' => now(),
        ]);
    }
}
