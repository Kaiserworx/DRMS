<?php

namespace App\Services;

use Illuminate\Support\Str;

class ReferenceNameNormalizer
{
    public static function display(string $value): string
    {
        return Str::squish($value);
    }

    public static function key(string $value): string
    {
        return Str::lower(self::display($value));
    }
}
