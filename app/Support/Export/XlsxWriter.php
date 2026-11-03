<?php

declare(strict_types=1);

namespace App\Support\Export;

use App\Reports\ColumnType;
use App\Reports\ReportColumn;
use App\Reports\ReportResult;
use App\Support\Csv\Cell as SafeCell;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use DateTimeImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a tabular result as a real .xlsx workbook (openspout through spatie/simple-excel).
 *
 * Typed cells: money and quantities are numeric cells with a number format (`#,##0.00`, `#,##0.###`),
 * so Excel sums them; dates are date cells (`yyyy-mm-dd`); text is formula-neutralised. The numeric
 * money cell is the one place a taka double exists: it is the file format's requirement, computed from
 * the integer paisa at the very edge and never read back.
 */
final class XlsxWriter
{
    public const string MONEY_FORMAT = '#,##0.00';

    public const string QTY_FORMAT = '#,##0.###';

    public const string DATE_FORMAT = 'yyyy-mm-dd';

    /** Basis points become an Excel percentage cell (1234 → 0.1234 shown as 12.34%). */
    public const string PERCENT_FORMAT = '0.00%';

    public static function stream(ReportResult $result, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($result): void {
            // Written straight to the response body: openspout's own openToBrowser() would replace the headers Laravel already sent.
            $writer = SimpleExcelWriter::create('php://output', 'xlsx');
            self::fill($writer, $result);
            $writer->close();
        }, $filename, ['Content-Type' => ExportFormat::Xlsx->contentType()]);
    }

    /** Writes the workbook to a path (tests, queued exports). */
    public static function write(ReportResult $result, string $path): void
    {
        $writer = SimpleExcelWriter::create($path);
        self::fill($writer, $result);
        $writer->close();
    }

    private static function fill(SimpleExcelWriter $writer, ReportResult $result): void
    {
        $writer->noHeaderRow();
        $header = new Style(fontBold: true);
        $writer->addRow(new Row(array_map(
            static fn (ReportColumn $c): Cell => Cell::fromValue($c->label, $header->withCellAlignment($c->type->isNumeric() ? CellAlignment::RIGHT : CellAlignment::LEFT)),
            $result->columns,
        )));

        foreach ($result->rows as $row) {
            $writer->addRow(new Row(array_map(
                static fn (ReportColumn $c): Cell => self::cell($c, $row[$c->key] ?? null),
                $result->columns,
            )));
        }

        if ($result->totals !== null) {
            $cells = [];
            foreach ($result->columns as $i => $column) {
                $cells[] = array_key_exists($column->key, $result->totals)
                    ? self::cell($column, $result->totals[$column->key], bold: true)
                    : Cell::fromValue($i === 0 ? 'Total' : '', $header);
            }
            $writer->addRow(new Row($cells));
        }
    }

    public static function cell(ReportColumn $column, int|string|null $value, bool $bold = false): Cell
    {
        $style = new Style(fontBold: $bold);
        if ($value === null || $value === '') {
            return Cell::fromValue('', $style);
        }

        return match ($column->type) {
            ColumnType::Money => Cell::fromValue((float) Money::ofPaisa((int) $value)->toDecimalString(), $style->withFormat(self::MONEY_FORMAT)),
            ColumnType::Qty => Cell::fromValue((float) Quantity::ofMilli((int) $value)->toDecimalString(), $style->withFormat(self::QTY_FORMAT)),
            ColumnType::Int => Cell::fromValue((int) $value, $style),
            ColumnType::Percent => Cell::fromValue(((int) $value) / 10_000, $style->withFormat(self::PERCENT_FORMAT)),
            ColumnType::Date => self::dateCell((string) $value, $style),
            ColumnType::Text => Cell::fromValue(SafeCell::safe((string) $value), $style),
        };
    }

    private static function dateCell(string $value, Style $style): Cell
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false
            ? Cell::fromValue(SafeCell::safe($value), $style)
            : Cell::fromValue($date, $style->withFormat(self::DATE_FORMAT));
    }
}
