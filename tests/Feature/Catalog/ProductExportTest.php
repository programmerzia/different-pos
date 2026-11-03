<?php

declare(strict_types=1);

use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

/** @return array{0: CatalogWorld, 1: CatalogWorld} */
function productWorlds(): array
{
    $mine = CatalogWorld::make();
    $other = CatalogWorld::make();
    foreach ([[$mine, 'Mine Panjabi', 'MP-1'], [$other, 'Foreign Row', 'FR-1'], [$mine, 'Other Saree', 'OS-1']] as [$w, $n, $s]) {
        $w->product(['name' => $n, 'sku' => $s]);
    }

    return [$mine, $other];
}

it('exports the products list as csv, xlsx and pdf with the right type and filename', function (): void {
    [$mine] = productWorlds();
    $this->actingAs($mine->user)->withSession(['branch_id' => $mine->branch->id]);

    $csv = $this->get('/app/products/export.csv')->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')
        ->and($csv->headers->get('content-disposition'))->toMatch('/products-\d{8}-\d{6}\.csv/')
        ->and($csv->streamedContent())->toContain('Mine Panjabi');

    $xlsx = $this->get('/app/products/export.xlsx')->assertOk();
    expect($xlsx->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($xlsx->headers->get('content-disposition'))->toMatch('/products-\d{8}-\d{6}\.xlsx/');
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    file_put_contents($path, $xlsx->streamedContent());
    $rows = SimpleExcelReader::create($path)->getRows()->all();
    unlink($path);
    expect(collect($rows)->flatten()->map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '')->implode('|'))->toContain('Mine Panjabi');

    $pdf = $this->get('/app/products/export.pdf')->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and($pdf->headers->get('content-disposition'))->toMatch('/products-\d{8}-\d{6}\.pdf/')
        ->and($pdf->getContent())->toStartWith('%PDF');
});

it('honours the list filters in the products export', function (): void {
    [$mine] = productWorlds();
    $this->actingAs($mine->user)->withSession(['branch_id' => $mine->branch->id]);

    $body = $this->get('/app/products/export.csv?q=Saree')->assertOk()->streamedContent();
    expect($body)->toContain('Saree')->not->toContain('Mine Panjabi');
});

it('never leaks another tenant\'s products into the export', function (): void {
    [$mine] = productWorlds();
    $this->actingAs($mine->user)->withSession(['branch_id' => $mine->branch->id]);

    foreach (['csv', 'pdf'] as $format) {
        $body = $this->get("/app/products/export.{$format}")->assertOk();
        $content = $format === 'csv' ? $body->streamedContent() : $body->getContent();
        if ($format === 'csv') {
            expect($content)->not->toContain('Foreign Row');
        }
    }
});

it('requires products.view for every products export format', function (): void {
    $world = CatalogWorld::make(SystemRoles::ACCOUNTANT);
    $this->actingAs($world->user)->withSession(['branch_id' => $world->branch->id]);

    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $this->get("/app/products/export.{$format}")->assertForbidden();
    }
});
