<?php

namespace App\Enums;

enum PhysicalLocation: string
{
    case OrganizationalUnit = 'organizational_unit';
    case ManagingOffice = 'managing_office';
    case UpstreamOffice = 'upstream_office';
    case ReceivingBox = 'receiving_box';
    case RecipientUnit = 'recipient_unit';

    public function label(): string
    {
        return match ($this) {
            self::OrganizationalUnit => 'Organizational Unit',
            self::ManagingOffice => 'Managing Office',
            self::UpstreamOffice => 'Upstream Office',
            self::ReceivingBox => 'Receiving Box',
            self::RecipientUnit => 'Recipient Unit',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(
                fn (self $location): array => [
                    'value' => $location->value,
                    'label' => $location->label(),
                ],
                self::cases(),
            ),
            'label',
            'value',
        );
    }
}
