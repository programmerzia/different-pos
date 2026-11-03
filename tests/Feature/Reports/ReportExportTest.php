<?php

declare(strict_types=1);

use App\Reports\ColumnType;
use App\Reports\ReportColumn;
use App\Reports\ReportContract;
use App\Reports\ReportParameters;
use App\Reports\ReportResult;
use App\Reports\SalesByDay;
use App\Support\Pdf\PdfRenderer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-03 08:00:00', 'UTC'));
    $this->world = SalesWorld::make();
    $this->product = $this->world->product(100_000, 60_000);
    $this->world->stock($this->product, 5, 60_000);
    $this->customer = $this->world->customer(['name' => 'রহিম স্টোর']);
    $this->world->sell([$this->world->line($this->product, 2)], [$this->world->tender('cash', 200_000)], $this->customer, soldAt: Carbon::parse('2026-11-03 06:00:00', 'UTC'));
    foreach ($this->world->users as $user) {
        $user->branches()->attach($this->world->branch);
    }
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->world->tenant->id);
    $this->withSession(['branch_id' => $this->world->branch->id]);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('exports a report as CSV by default, named <report>-<from>-<to>.csv', function (): void {
    $response = $this->actingAs($this->world->user('Owner'))
        ->post(route('reports.export', ['report' => 'sales-by-day', 'from' => '2026-11-01', 'to' => '2026-11-03']))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('sales-by-day-2026-11-01-2026-11-03.csv')
        ->and($response->streamedContent())->toContain('2026-11-03')->toContain('2000.00');
});

it('exports a report as a typed xlsx workbook', function (): void {
    $response = $this->actingAs($this->world->user('Owner'))
        ->post(route('reports.export', ['report' => 'sales-by-day', 'format' => 'xlsx', 'from' => '2026-11-01', 'to' => '2026-11-03']))
        ->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->headers->get('content-disposition'))->toContain('sales-by-day-2026-11-01-2026-11-03.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $rows = SimpleExcelReader::create($path)->getRows()->all();
    unlink($path);

    expect($rows)->not->toBeEmpty();
    $day = collect($rows)->first(static fn (array $r): bool => isset($r['Date']) && $r['Date'] instanceof DateTimeInterface && $r['Date']->format('Y-m-d') === '2026-11-03');
    expect($day)->not->toBeNull()
        ->and($day['Gross sales'])->toEqual(2000);
});

it('exports a report as a PDF with the business header', function (): void {
    $response = $this->actingAs($this->world->user('Owner'))
        ->post(route('reports.export', ['report' => 'sales-by-day', 'format' => 'pdf', 'from' => '2026-11-01', 'to' => '2026-11-03']))
        ->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('sales-by-day-2026-11-01-2026-11-03.pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

it('refuses a PDF above the row cap with a clear validation message instead of truncating', function (): void {
    $huge = new class implements ReportContract
    {
        public function name(): string
        {
            return 'sales-by-day';
        }

        public function group(): string
        {
            return 'sales';
        }

        public function usesPeriod(): bool
        {
            return true;
        }

        public function filters(): array
        {
            return [];
        }

        public function run(ReportParameters $parameters): ReportResult
        {
            return new ReportResult([new ReportColumn('n', 'N', ColumnType::Int)], array_map(static fn (int $i): array => ['n' => $i], range(1, PdfRenderer::MAX_TABLE_ROWS + 1)));
        }
    };
    // The registry resolves every report from the container, so SalesByDay can be swapped for the stub.
    $this->app->instance(SalesByDay::class, $huge);

    $this->actingAs($this->world->user('Owner'))
        ->postJson(route('reports.export', ['report' => 'sales-by-day', 'format' => 'pdf']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.format.0', fn (string $m): bool => str_contains($m, '2,001 rows') && str_contains($m, '2,000'));

    // The same rows are fine as a stream.
    $this->actingAs($this->world->user('Owner'))
        ->post(route('reports.export', ['report' => 'sales-by-day', 'format' => 'xlsx']))
        ->assertOk();
});

it('rejects an unknown format and requires reports.export for every format', function (): void {
    $owner = $this->world->user('Owner');
    $this->actingAs($owner)->postJson(route('reports.export', ['report' => 'sales-by-day', 'format' => 'docx']))->assertUnprocessable()->assertJsonValidationErrors('format');

    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $this->actingAs($this->world->user('Stock Keeper'))->post(route('reports.export', ['report' => 'sales-by-day', 'format' => $format]))->assertForbidden();
    }
});

it('never leaks another tenant\'s rows into an export', function (): void {
    $other = app(TenantContext::class)->withoutTenancy(static fn (): SalesWorld => SalesWorld::make());
    app(TenantContext::class)->set($other->tenant, $other->branch);
    $product = $other->product(999_900, 1_000);
    $other->stock($product, 5, 1_000);
    $other->sell([$other->line($product, 1)], [$other->tender('cash', 999_900)], $other->customer(['name' => 'Foreign Customer']), soldAt: Carbon::parse('2026-11-03 06:00:00', 'UTC'));
    app(TenantContext::class)->set($this->world->tenant, $this->world->branch);

    $owner = $this->world->user('Owner');
    foreach (['csv', 'xlsx'] as $format) {
        $response = $this->actingAs($owner)->post(route('reports.export', ['report' => 'top-customers', 'format' => $format, 'from' => '2026-11-01', 'to' => '2026-11-03']))->assertOk();
        $body = $response->streamedContent();
        if ($format === 'csv') {
            expect($body)->toContain('রহিম স্টোর')->not->toContain('Foreign Customer')->not->toContain('9999.00');
        } else {
            $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
            file_put_contents($path, $body);
            $cells = collect(SimpleExcelReader::create($path)->noHeaderRow()->getRows()->all())->flatten()->map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '')->implode('|');
            unlink($path);
            expect($cells)->toContain('রহিম স্টোর')->not->toContain('Foreign Customer');
        }
    }
});
