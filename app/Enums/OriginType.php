<?php

namespace App\Enums;

enum OriginType: string
{
    case OrganizationalUnit = 'organizational_unit';
    case ManagingOffice = 'managing_office';
    case UpstreamOffice = 'upstream_office';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::OrganizationalUnit => 'Organizational Unit',
            self::ManagingOffice => 'Managing Office',
            self::UpstreamOffice => 'Upstream Office',
            self::External => 'External Organization',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(
                fn (self $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                self::cases(),
            ),
            'label',
            'value',
        );
    }
}
