<?php

namespace App\Enums;

enum OrganizationalUnitType: string
{
    case School = 'school';
    case District = 'district';
    case Division = 'division';
    case Section = 'section';
    case FunctionalUnit = 'functional_unit';

    public function label(): string
    {
        return match ($this) {
            self::School => 'School',
            self::District => 'District',
            self::Division => 'Division',
            self::Section => 'Section',
            self::FunctionalUnit => 'Functional Unit',
        };
    }

    /**
     * @param  array<self>  $types
     * @return array<string, string>
     */
    public static function options(array $types): array
    {
        return array_column(
            array_map(
                fn (self $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                $types,
            ),
            'label',
            'value',
        );
    }
}
