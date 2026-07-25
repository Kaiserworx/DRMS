<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ReceivedAtManagingOffice = 'received_at_managing_office';
    case ForwardedToUpstreamOffice = 'forwarded_to_upstream_office';
    case ReceivedAtUpstreamOffice = 'received_at_upstream_office';
    case ReturnedFromUpstreamOffice = 'returned_from_upstream_office';
    case ForDistribution = 'for_distribution';
    case ReadyForPickup = 'ready_for_pickup';
    case PartiallyClaimed = 'partially_claimed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::ReceivedAtManagingOffice => 'Received at Managing Office',
            self::ForwardedToUpstreamOffice => 'Forwarded to Upstream Office',
            self::ReceivedAtUpstreamOffice => 'Received at Upstream Office',
            self::ReturnedFromUpstreamOffice => 'Returned from Upstream Office',
            self::ForDistribution => 'For Distribution',
            self::ReadyForPickup => 'Ready for Pickup',
            self::PartiallyClaimed => 'Partially Claimed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
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
