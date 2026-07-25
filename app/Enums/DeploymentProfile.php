<?php

namespace App\Enums;

enum DeploymentProfile: string
{
    case District = 'district';
    case Division = 'division';
    case Regional = 'regional';

    public function label(): string
    {
        return match ($this) {
            self::District => 'District',
            self::Division => 'Division',
            self::Regional => 'Regional',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(
                fn (self $profile): array => [
                    'value' => $profile->value,
                    'label' => $profile->label(),
                ],
                self::cases(),
            ),
            'label',
            'value',
        );
    }
}
