<?php

namespace App\Enums;

enum UserRole: string
{
    case LevelOne = 'level_1';
    case LevelTwo = 'level_2';

    public function label(): string
    {
        return match ($this) {
            self::LevelOne => 'Level 1',
            self::LevelTwo => 'Level 2',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(
                fn (self $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ],
                self::cases(),
            ),
            'label',
            'value',
        );
    }
}
