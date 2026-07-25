<?php

namespace App\Enums;

enum RecipientStatus: string
{
    case Assigned = 'assigned';
    case ReadyForPickup = 'ready_for_pickup';
    case ReceivedByRecipientUnit = 'received_by_recipient_unit';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::ReadyForPickup => 'Ready for Pickup',
            self::ReceivedByRecipientUnit => 'Received by Recipient Unit',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(
                fn (self $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                self::cases(),
            ),
            'label',
            'value',
        );
    }
}
