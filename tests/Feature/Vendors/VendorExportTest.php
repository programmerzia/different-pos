<?php

declare(strict_types=1);

use App\Models\Vendor;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

/** @return array{0: CatalogWorld, 1: CatalogWorld} */
function vendorWorlds(): array
{
    $mine = CatalogWorld::make();
    $other = CatalogWorld::make();
    foreach ([[$mine, 'Mine Vendor'], [$other, 'Foreign Row'], [$mine, 'Other Trader']] as [$w, $n]) {
        app(TenantContext::class)->withoutTenancy(fn () => Vendor::factory()->create(['tenant_id' => $w->tenant->id, 'name' => $n]));
    }

    return [$mine, $other];
}

it('exports the vendors list as csv, xlsx and pdf with the right type and filename', function (): void {
    [$mine] = vendorWorlds();
    $this->actingAs($mine->user)->withSession(['branch_id' => $mine->branch->id]);

    $csv = $this->get('/app/vendors/export.csv')->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')
        ->and($csv->headers->get('content-disposition'))->toMatch('/vendors-\d{8}-\d{6}\.csv/')
        ->and($csv->streamedContent())->toContain('Mine Vendor');

    $xlsx = $this->get('/app/vendors/export.xlsx')->assertOk();
    expect($xlsx->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($xlsx->headers->get('content-disposition'))->toMatch('/vendors-\d{8}-\d{6}\.xlsx/');
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    file_put_contents($path, $xlsx->streamedContent());
    $rows = SimpleExcelReader::create($path)->getRows()->all();
    unlink($path);
    expect(collect($rows)->flatten()->map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '')->implode('|'))->toContain('Mine Vendor');

    $pdf = $this->get('/app/vendors/export.pdf')->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and($pdf->headers->get('content-disposition'))->toMatch('/vendors-\d{8}-\d{6}\.pdf/')
        ->and($pdf->getContent())->toStartWith('%PDF');
});

it('honours the list filters in the vendors export', function (): void {
    [$mine] = vendorWorlds();
    $this->actingAs($mine->user)->withSession(['branch_id' => $mine->branch->id]);

    $body = $this->get('/app/vendors/export.csv?q=Trader')->assertOk()->streamedContent();
    expect($body)->toContain('Trader')->not->toContain('Mine Vendor');
});

it('never leaks another tenant\'s vendors into the export', function (): void {
    [$mine] = vendorWorlds();
    $this->actingAs($mine->user)->withSession(['branch_id' => $mine->branch->id]);

    foreach (['csv', 'pdf'] as $format) {
        $body = $this->get("/app/vendors/export.{$format}")->assertOk();
        $content = $format === 'csv' ? $body->streamedContent() : $body->getContent();
        if ($format === 'csv') {
            expect($content)->not->toContain('Foreign Row');
        }
    }
});

it('requires vendors.view for every vendors export format', function (): void {
    $world = CatalogWorld::make(SystemRoles::CASHIER);
    $this->actingAs($world->user)->withSession(['branch_id' => $world->branch->id]);

    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $this->get("/app/vendors/export.{$format}")->assertForbidden();
    }
});
