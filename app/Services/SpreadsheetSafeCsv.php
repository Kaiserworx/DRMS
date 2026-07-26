<?php

namespace App\Services;

use Generator;

class SpreadsheetSafeCsv
{
    /**
     * @param  resource  $stream
     * @param  array<string, string>  $columns
     * @param  Generator<int, array<string, scalar|null>>  $rows
     */
    public function write($stream, array $columns, Generator $rows): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_values($columns));

        foreach ($rows as $row) {
            fputcsv(
                $stream,
                array_map(
                    fn (string $key): string|int|float => $this->safeValue($row[$key] ?? null),
                    array_keys($columns),
                ),
            );
        }
    }

    public function safeValue(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $value = (string) ($value ?? '');

        return preg_match('/^[\p{Z}\s]*[=+\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }
}
