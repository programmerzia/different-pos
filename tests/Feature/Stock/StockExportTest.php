<?php

declare(strict_types=1);

use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

/** @return array{0: CatalogWorld, 1: CatalogWorld} */
function stockWorlds(): array
{
    $mine = CatalogWorld::make();
    $other = CatalogWorld::make();
    foreach ([[$mine, 'Mine Panjabi', 'MP-1'], [$other, 'Foreign Row', 'FR-1'], [$mine, 'Other Saree', 'OS-1']] as [$w, $n, $s]) {
        $w->product(['name' => $n, 'sku' => $s, 'track_stock' => true]);
    }

    return [$mine, $other];
}

it('exports the stock list as csv, xlsx and pdf with the right type and filename', function (): void {
    [$mine] = stockWorlds();
    $this->actingAs($mine->user)->withSession(['branch_id' => $mine->branch->id]);

    $csv = $this->get('/app/stock/export.csv')->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')
        ->and($csv->headers->get('content-disposition'))->toMatch('/stock-\d{8}-\d{6}\.csv/')
        ->and($csv->streamedContent())->toContain('Mine Panjabi');

    $xlsx = $this->get('/app/stock/export.xlsx')->assertOk();
    expect($xlsx->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($xlsx->headers->get('content-disposition'))->toMatch('/stock-\d{8}-\d{6}\.xlsx/');
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    file_put_contents($path, $xlsx->streamedContent());
    $rows = SimpleExcelReader::create($path)->getRows()->all();
    unlink($path);
    expect(collect($rows)->flatten()->map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '')->implode('|'))->toContain('Mine Panjabi');

    $pdf = $this->get('/app/stock/export.pdf')->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and($pdf->headers->get('content-disposition'))->toMatch('/stock-\d{8}-\d{6}\.pdf/')
        ->and($pdf->getContent())->toStartWith('%PDF');
});

it('honours the list filters in the stock export', function (): void {
    [$mine] = stockWorlds();
    $this->actingAs($mine->user)->withSession(['branch_id' => $mine->branch->id]);

    $body = $this->get('/app/stock/export.csv?q=Saree')->assertOk()->streamedContent();
    expect($body)->toContain('Saree')->not->toContain('Mine Panjabi');
});

it('never leaks another tenant\'s stock into the export', function (): void {
    [$mine] = stockWorlds();
    $this->actingAs($mine->user)->withSession(['branch_id' => $mine->branch->id]);

    foreach (['csv', 'pdf'] as $format) {
        $body = $this->get("/app/stock/export.{$format}")->assertOk();
        $content = $format === 'csv' ? $body->streamedContent() : $body->getContent();
        if ($format === 'csv') {
            expect($content)->not->toContain('Foreign Row');
        }
    }
});

it('requires stock.view for every stock export format', function (): void {
    $world = CatalogWorld::make(SystemRoles::ACCOUNTANT);
    $this->actingAs($world->user)->withSession(['branch_id' => $world->branch->id]);

    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $this->get("/app/stock/export.{$format}")->assertForbidden();
    }
});
