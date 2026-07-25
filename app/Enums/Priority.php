<?php

namespace App\Enums;

enum Priority: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Urgent => 'Urgent',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(
                fn (self $priority): array => [
                    'value' => $priority->value,
                    'label' => $priority->label(),
                ],
                self::cases(),
            ),
            'label',
            'value',
        );
    }
}
