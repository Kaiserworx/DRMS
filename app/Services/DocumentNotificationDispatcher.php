<?php

namespace App\Services;

use App\Enums\DocumentNotificationType;
use App\Enums\OperationalStatus;
use App\Enums\RoutingAction;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentTransaction;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentNotificationDispatcher
{
    public function dispatchForTransaction(DocumentTransaction $transaction): int
    {
        try {
            $transaction->loadMissing(['document', 'recipient']);

            if ($transaction->action !== RoutingAction::PlaceInReceivingBox || $transaction->recipient === null) {
                return 0;
            }

            return $this->dispatchToUnits(
                document: $transaction->document,
                type: DocumentNotificationType::Placement,
                eventKey: "transaction:{$transaction->getKey()}:".DocumentNotificationType::Placement->value,
                unitIds: [$transaction->recipient->recipient_unit_id],
                message: "{$transaction->document->tracking_no} is ready in your receiving box.",
            );
        } catch (Throwable $exception) {
            $this->reportFailure('transaction', (string) $transaction->getKey(), $exception);

            return 0;
        }
    }

    /**
     * @param  array<int, int>  $unitIds
     */
    private function dispatchToUnits(
        Document $document,
        DocumentNotificationType $type,
        string $eventKey,
        array $unitIds,
        string $message,
    ): int {
        if ($unitIds === []) {
            return 0;
        }

        /** @var Collection<int, User> $users */
        $users = User::query()
            ->where('role', UserRole::LevelOne->value)
            ->where('status', OperationalStatus::Active->value)
            ->whereIn('organizational_unit_id', array_values(array_unique($unitIds)))
            ->orderBy('id')
            ->get();
        $queued = 0;

        foreach ($users as $user) {
            $notificationId = $this->notificationId($eventKey, $user->getKey());
            try {
                $inserted = DB::table('notification_dispatches')->insertOrIgnore([
                    'event_key' => $eventKey,
                    'user_id' => $user->getKey(),
                    'notification_id' => $notificationId,
                    'created_at' => now(),
                ]);

                if ($inserted !== 1) {
                    continue;
                }

                $user->notify(new DocumentWorkflowNotification(
                    id: $notificationId,
                    documentId: $document->getKey(),
                    trackingNumber: $document->tracking_no,
                    eventType: $type,
                    title: 'Document ready for pickup',
                    message: $message,
                    safeUrl: url("/admin/documents/{$document->getKey()}"),
                ));
                $queued++;
            } catch (Throwable $exception) {
                DB::table('notification_dispatches')
                    ->where('event_key', $eventKey)
                    ->where('user_id', $user->getKey())
                    ->delete();
                $this->reportFailure($type->value, $eventKey, $exception);
            }
        }

        return $queued;
    }

    private function notificationId(string $eventKey, int $userId): string
    {
        $hex = substr(hash('sha256', "{$eventKey}:user:{$userId}"), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }

    private function reportFailure(string $eventType, string $eventKey, Throwable $exception): void
    {
        Log::error('DRMS notification dispatch failed.', [
            'event_type' => $eventType,
            'event_key' => $eventKey,
            'exception' => $exception::class,
        ]);
    }
}
