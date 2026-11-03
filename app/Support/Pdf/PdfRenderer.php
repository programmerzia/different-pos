<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use App\Reports\ColumnType;
use App\Reports\ReportColumn;
use App\Reports\ReportCsv;
use App\Reports\ReportResult;
use App\Support\Export\ExportMeta;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use App\Support\Quantity\Quantity;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * The app's one PDF engine (mPDF 8, ADR 0009). Bangla is shaped by mPDF's OpenType layout with the bundled
 * Hind Siliguri (resources/fonts, OFL), which is also the default font so EN and BN mix on one line.
 *
 * `table()` prints a tabular result (a report or a list export) on A4 with the tenant header (logo, business,
 * branch), the period, a generated-at stamp in the tenant timezone and page numbers; money is right-aligned
 * and formatted with ৳ by the shared MoneyFormatter. `html()` is the lower-level entry for other documents.
 */
final class PdfRenderer
{
    public const string FONT = 'hindsiliguri';

    /** Rows above this are refused with a validation error (no silent truncation): the caller should offer CSV/XLSX. */
    public const int MAX_TABLE_ROWS = 2000;

    /** Portrait fits comfortably up to this many columns; wider tables go landscape. */
    public const int PORTRAIT_MAX_COLUMNS = 6;

    public const string PORTRAIT = 'P';

    public const string LANDSCAPE = 'L';

    /** Renders a tabular result; orientation is picked from the column count unless given. */
    public function table(ReportResult $result, ExportMeta $meta, ?string $orientation = null): string
    {
        $orientation ??= count($result->columns) > self::PORTRAIT_MAX_COLUMNS ? self::LANDSCAPE : self::PORTRAIT;

        $html = view('exports.table', [
            'meta' => $meta,
            'columns' => $result->columns,
            'rows' => array_map(fn (array $row): array => $this->cells($result->columns, $row), $result->rows),
            'totals' => $result->totals === null ? null : $this->totalCells($result->columns, $result->totals),
            'generated' => $meta->generatedAt->format('d M Y, h:i A').' ('.$meta->timezone.')',
        ])->render();

        return $this->html($html, $orientation, footer: view('exports.footer', ['meta' => $meta])->render());
    }

    /**
     * Renders an HTML document to PDF bytes.
     *
     * @param  string|array{float, float}  $format  `A4` or a `[width, height]` in mm
     */
    public function html(string $html, string $orientation = self::PORTRAIT, string|array $format = 'A4', ?string $header = null, ?string $footer = null, int $margin = 12): string
    {
        $mpdf = $this->mpdf($format, $orientation, $margin);
        if ($header !== null) {
            $mpdf->SetHTMLHeader($header);
        }
        if ($footer !== null) {
            $mpdf->SetHTMLFooter($footer);
        }
        $mpdf->WriteHTML($html, HTMLParserMode::DEFAULT_MODE);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /** @param string|array{float, float} $format */
    public function mpdf(string|array $format = 'A4', string $orientation = self::PORTRAIT, int $margin = 12): Mpdf
    {
        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        /** @var array<string, mixed> $defaults */
        $defaults = (new ConfigVariables)->getDefaults();
        /** @var array<string, mixed> $fontDefaults */
        $fontDefaults = (new FontVariables)->getDefaults();
        /** @var list<string> $fontDirs */
        $fontDirs = $defaults['fontDir'];
        /** @var array<string, array<string, mixed>> $fontData */
        $fontData = $fontDefaults['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => is_array($format) ? $format : $format.'-'.$orientation,
            'orientation' => $orientation,
            'tempDir' => $tempDir,
            'fontDir' => [...$fontDirs, resource_path('fonts')],
            'fontdata' => $fontData + [
                self::FONT => [
                    'R' => 'HindSiliguri-Regular.ttf',
                    'B' => 'HindSiliguri-Bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => self::FONT,
            'default_font_size' => 9,
            'margin_left' => $margin,
            'margin_right' => $margin,
            'margin_top' => $margin,
            'margin_bottom' => $margin + 4,
            'margin_header' => 5,
            'margin_footer' => 5,
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
        ]);
        $mpdf->SetTitle('');
        $mpdf->SetCreator('POS');
        $mpdf->shrink_tables_to_fit = 1;

        return $mpdf;
    }

    /**
     * @param  list<ReportColumn>  $columns
     * @param  array<string, int|string|null>  $row
     * @return list<array{text: string, numeric: bool}>
     */
    private function cells(array $columns, array $row): array
    {
        return array_map(fn (ReportColumn $c): array => ['text' => $this->cell($c, $row[$c->key] ?? null), 'numeric' => $c->type->isNumeric()], $columns);
    }

    /**
     * @param  list<ReportColumn>  $columns
     * @param  array<string, int>  $totals
     * @return list<array{text: string, numeric: bool}>
     */
    private function totalCells(array $columns, array $totals): array
    {
        $cells = [];
        foreach ($columns as $i => $column) {
            $cells[] = [
                'text' => array_key_exists($column->key, $totals) ? $this->cell($column, $totals[$column->key]) : ($i === 0 ? 'Total' : ''),
                'numeric' => $column->type->isNumeric(),
            ];
        }

        return $cells;
    }

    public function cell(ReportColumn $column, int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return $column->type->isNumeric() ? '' : '-';
        }

        return match ($column->type) {
            ColumnType::Money => MoneyFormatter::format(Money::ofPaisa((int) $value)),
            ColumnType::Qty => Quantity::ofMilli((int) $value)->toDecimalString(),
            ColumnType::Int => number_format((int) $value),
            ColumnType::Percent => ReportCsv::cell($column, $value).' %',
            ColumnType::Date, ColumnType::Text => (string) $value,
        };
    }
}
