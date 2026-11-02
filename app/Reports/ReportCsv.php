<?php

declare(strict_types=1);

namespace App\Reports;

use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a report as CSV: plain decimals for money and quantities, never formatted display text. */
final class ReportCsv
{
    public static function stream(ReportResult $result, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($result): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Bangla names
            fputcsv($out, array_map(static fn (ReportColumn $c): string => $c->label, $result->columns), ',', '"', '');

            foreach ($result->rows as $row) {
                fputcsv($out, self::cells($result->columns, $row), ',', '"', '');
            }
            if ($result->totals !== null) {
                $totals = ['__first' => 'Total'] + $result->totals;
                $cells = [];
                foreach ($result->columns as $i => $column) {
                    $cells[] = array_key_exists($column->key, $result->totals)
                        ? self::cell($column, $result->totals[$column->key])
                        : ($i === 0 ? $totals['__first'] : '');
                }
                fputcsv($out, $cells, ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  list<ReportColumn>  $columns
     * @param  array<string, int|string|null>  $row
     * @return list<string>
     */
    public static function cells(array $columns, array $row): array
    {
        return array_map(static fn (ReportColumn $c): string => self::cell($c, $row[$c->key] ?? null), $columns);
    }

    public static function cell(ReportColumn $column, int|string|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return match ($column->type) {
            ColumnType::Money => Money::ofPaisa((int) $value)->toDecimalString(),
            ColumnType::Qty => Quantity::ofMilli((int) $value)->toDecimalString(),
            ColumnType::Int, ColumnType::Date => (string) $value,
            ColumnType::Text => self::safeText((string) $value),
        };
    }

    /** Neutralises spreadsheet formulas in user-entered text. */
    private static function safeText(string $text): string
    {
        return $text !== '' && str_contains("=+-@\t\r", $text[0]) ? "'".$text : $text;
    }
}
