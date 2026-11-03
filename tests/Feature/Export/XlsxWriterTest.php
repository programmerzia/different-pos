<?php

declare(strict_types=1);

use App\Reports\ColumnType;
use App\Reports\ReportColumn;
use App\Reports\ReportResult;
use App\Support\Export\XlsxWriter;
use Spatie\SimpleExcel\SimpleExcelReader;

function xlsxResult(): ReportResult
{
    return new ReportResult(
        [
            new ReportColumn('day', 'Day', ColumnType::Date),
            new ReportColumn('name', 'Customer', ColumnType::Text),
            new ReportColumn('qty', 'Qty', ColumnType::Qty),
            new ReportColumn('count', 'Invoices', ColumnType::Int),
            new ReportColumn('net', 'Net', ColumnType::Money),
            new ReportColumn('share', 'Share', ColumnType::Percent),
        ],
        [
            ['day' => '2026-11-03', 'name' => 'রহিম স্টোর', 'qty' => 2_500, 'count' => 3, 'net' => 12_345_650, 'share' => 1_234],
            ['day' => '2026-11-02', 'name' => '=HYPERLINK("x")', 'qty' => 1_000, 'count' => 1, 'net' => -5_000, 'share' => null],
        ],
        ['qty' => 3_500, 'count' => 4, 'net' => 12_340_650],
    );
}

it('writes typed cells: numeric money and quantities, real dates, neutralised text, a bold totals row', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    XlsxWriter::write(xlsxResult(), $path);

    $rows = SimpleExcelReader::create($path)->noHeaderRow()->getRows()->all();
    expect($rows)->toHaveCount(4)
        ->and(array_values($rows[0]))->toBe(['Day', 'Customer', 'Qty', 'Invoices', 'Net', 'Share'])
        ->and($rows[1][0])->toBeInstanceOf(DateTimeInterface::class)
        ->and($rows[1][0]->format('Y-m-d'))->toBe('2026-11-03')
        ->and($rows[1][1])->toBe('রহিম স্টোর')
        ->and($rows[1][2])->toBe(2.5)
        ->and($rows[1][3])->toBe(3)
        ->and($rows[1][4])->toBe(123456.5)
        ->and($rows[1][5])->toBe(0.1234)
        ->and($rows[2][1])->toBe("'=HYPERLINK(\"x\")")
        ->and($rows[2][4])->toEqual(-50)
        ->and($rows[2][5])->toBe('')
        ->and($rows[3][0])->toBe('Total')
        ->and($rows[3][4])->toBe(123406.5);

    // The styles part carries the number formats (money `#,##0.00` and `0.00%` are Excel built-ins 4 and 10;
    // the date and quantity formats are custom), so Excel shows grouping and sums the column.
    $zip = new ZipArchive;
    $zip->open($path);
    $styles = (string) $zip->getFromName('xl/styles.xml');
    $zip->close();
    expect($styles)->toContain(XlsxWriter::DATE_FORMAT)->toContain(XlsxWriter::QTY_FORMAT)
        ->toContain('numFmtId="4"')->toContain('numFmtId="10"');
    unlink($path);
});

it('streams the workbook as a download with the xlsx content type', function (): void {
    $response = XlsxWriter::stream(xlsxResult(), 'sales-by-day-2026-11-01-2026-11-03.xlsx');

    expect($response->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->headers->get('content-disposition'))->toContain('sales-by-day-2026-11-01-2026-11-03.xlsx');

    ob_start();
    $response->sendContent();
    $bytes = (string) ob_get_clean();
    expect(substr($bytes, 0, 2))->toBe('PK');
});
