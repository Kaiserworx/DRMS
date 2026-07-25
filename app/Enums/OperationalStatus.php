<?php

namespace App\Enums;

enum OperationalStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
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
