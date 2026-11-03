<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Tenant;
use App\Reports\ColumnType;
use App\Reports\ReportColumn;
use App\Reports\ReportResult;
use App\Support\Export\ExportMeta;
use App\Support\Pdf\PdfRenderer;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** The PDF's text layer via poppler (skipped where pdftotext is not installed). */
function pdfText(string $pdf): string
{
    $bin = (new ExecutableFinder)->find('pdftotext');
    if ($bin === null) {
        test()->markTestSkipped('pdftotext (poppler-utils) is not installed');
    }
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, $pdf);
    $process = new Process([$bin, '-layout', $path, '-']);
    $process->mustRun();
    unlink($path);

    return $process->getOutput();
}

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('shapes Bangla text with the bundled Hind Siliguri and keeps it in the text layer', function (): void {
    $pdf = (new PdfRenderer)->html('<p>ঢাকা মিরপুর ৳১,২৩,৪৫৬.৫০ বিক্রয়</p>');

    expect($pdf)->toStartWith('%PDF')
        ->and($pdf)->toContain('HindSiliguri');
    $text = pdfText($pdf);
    // Shaped output: the i-kar of মিরপুর is reordered before its consonant in the glyph stream, and the Bangla digits survive.
    expect($text)->toContain('ঢাকা')->toContain('৳১,২৩,৪৫৬.৫০')->toContain('িমরপুর');
});

it('prints a report table with the tenant header, period, Dhaka timestamp, formatted money and page numbers', function (): void {
    Storage::fake('public');
    $image = imagecreatetruecolor(40, 40);
    ob_start();
    imagepng($image);
    Storage::disk('public')->put('tenants/logo.png', (string) ob_get_clean());
    $tenant = Tenant::factory()->create(['name' => 'আলিফ ফ্যাশন', 'branding' => ['logo_path' => 'tenants/logo.png'], 'timezone' => 'Asia/Dhaka']);
    app(TenantContext::class)->set($tenant, null);
    $branch = Branch::factory()->for($tenant)->create(['name' => 'Mirpur Branch']);
    app(TenantContext::class)->set($tenant, $branch);
    CarbonImmutable::setTestNow('2026-11-03 06:30:00 UTC');

    $result = new ReportResult(
        [new ReportColumn('day', 'Day', ColumnType::Date), new ReportColumn('name', 'Customer', ColumnType::Text), new ReportColumn('net', 'Net sales', ColumnType::Money)],
        [['day' => '2026-11-03', 'name' => 'রহিম স্টোর', 'net' => 12_345_650]],
        ['net' => 12_345_650],
    );
    $meta = ExportMeta::for(app(TenantContext::class), 'Sales by day', ExportMeta::periodLabel('2026-11-01', '2026-11-03'));
    expect($meta->tenantLogoPath)->not->toBeNull()->and($meta->timezone)->toBe('Asia/Dhaka');

    $text = pdfText((new PdfRenderer)->table($result, $meta));
    expect($text)->toContain('Mirpur Branch')->toContain('Sales by day')->toContain('2026-11-01 – 2026-11-03')
        ->toContain('03 Nov 2026, 12:30 PM')->toContain('(Asia/Dhaka)')
        ->toContain('৳1,23,456.50')->toContain('Total')->toContain('Page 1 / 1');
    CarbonImmutable::setTestNow();
});

it('picks landscape for wide tables and respects an explicit orientation', function (): void {
    $renderer = new PdfRenderer;
    $wide = array_map(static fn (int $i): ReportColumn => new ReportColumn("c{$i}", "Col {$i}", ColumnType::Int), range(1, 8));
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant, null);
    $meta = ExportMeta::for(app(TenantContext::class), 'Wide');

    $landscape = $renderer->table(new ReportResult($wide, []), $meta);
    $portrait = $renderer->table(new ReportResult(array_slice($wide, 0, 3), []), $meta);
    // A4 is 595.28 x 841.89 pt; the MediaBox is written width first.
    expect($landscape)->toMatch('/MediaBox \[0 0 841\.\d+ 595\.\d+\]/')
        ->and($portrait)->toMatch('/MediaBox \[0 0 595\.\d+ 841\.\d+\]/')
        ->and($renderer->table(new ReportResult($wide, []), $meta, PdfRenderer::PORTRAIT))->toMatch('/MediaBox \[0 0 595\.\d+ 841\.\d+\]/');
});
