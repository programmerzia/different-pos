<?php

declare(strict_types=1);

namespace App\Legacy;

use RuntimeException;

final class Csv
{
    /**
     * @param  list<string>  $header
     * @param  iterable<array<string, string|int>>  $rows
     */
    public static function write(string $path, array $header, iterable $rows): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create report directory [{$directory}].");
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Cannot write [{$path}].");
        }

        try {
            fputcsv($handle, $header, ',', '"', '');
            foreach ($rows as $row) {
                $line = [];
                foreach ($header as $column) {
                    $line[] = (string) ($row[$column] ?? '');
                }
                fputcsv($handle, $line, ',', '"', '');
            }
        } finally {
            fclose($handle);
        }
    }
}
