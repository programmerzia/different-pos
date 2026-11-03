<?php

declare(strict_types=1);

use App\Models\Purchase;
use App\Models\Vendor;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\SalesWorld;

/*
| Sales, returns, dues and purchases list exports share TabularExport with the reports: here the
| wiring per list (route + permission + the same filtered query as the page + tenant isolation).
*/

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-03 08:00:00', 'UTC'));
    $this->world = SalesWorld::make();
    $product = $this->world->product(100_000, 60_000);
    $this->world->stock($product, 10, 60_000);
    $this->customer = $this->world->customer(['name' => 'রহিম স্টোর', 'credit_limit' => Money::ofPaisa(1_000_000)]);
    // One paid sale and one on credit (a due).
    $this->world->sell([$this->world->line($product, 1)], [$this->world->tender('cash', 100_000)], $this->customer, soldAt: Carbon::parse('2026-11-03 06:00:00', 'UTC'));
    $this->world->sell([$this->world->line($product, 2)], [], $this->customer, soldAt: Carbon::parse('2026-11-02 06:00:00', 'UTC'));
    foreach ($this->world->users as $user) {
        $user->branches()->attach($this->world->branch);
    }
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->world->tenant->id);
    $this->withSession(['branch_id' => $this->world->branch->id]);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

// list => [a system role WITHOUT its view permission, text the owner's CSV must contain]
$lists = [
    'sales' => ['Stock Keeper', 'রহিম স্টোর'],
    'returns' => ['Stock Keeper', 'Return'],
    'dues' => ['Stock Keeper', 'রহিম স্টোর'],
    'purchases' => ['Cashier', 'GRN'],
];

it('exports every list as csv, xlsx and pdf with the right type and filename', function (string $list, string $forbiddenRole, string $needle): void {
    $owner = $this->world->user('Owner');

    $csv = $this->actingAs($owner)->get("/app/{$list}/export.csv")->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')
        ->and($csv->headers->get('content-disposition'))->toMatch("/{$list}-\d{8}-\d{6}\.csv/")
        ->and($csv->streamedContent())->toContain($needle);

    $xlsx = $this->actingAs($owner)->get("/app/{$list}/export.xlsx")->assertOk();
    expect($xlsx->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and(substr($xlsx->streamedContent(), 0, 2))->toBe('PK');

    $pdf = $this->actingAs($owner)->get("/app/{$list}/export.pdf")->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and($pdf->headers->get('content-disposition'))->toMatch("/{$list}-\d{8}-\d{6}\.pdf/")
        ->and($pdf->getContent())->toStartWith('%PDF');
})->with(array_map(static fn (string $k, array $v): array => [$k, ...$v], array_keys($lists), $lists));

it('requires the list permission and rejects unknown formats', function (string $list, string $forbiddenRole): void {
    $this->actingAs($this->world->user($forbiddenRole))->get("/app/{$list}/export.csv")->assertForbidden();
    $this->actingAs($this->world->user($forbiddenRole))->get("/app/{$list}/export.pdf")->assertForbidden();
    $this->actingAs($this->world->user('Owner'))->get("/app/{$list}/export.docx")->assertNotFound();
})->with(array_map(static fn (string $k, array $v): array => [$k, $v[0]], array_keys($lists), $lists));

it('honours the sales list filters and totals only completed sales', function (): void {
    $owner = $this->world->user('Owner');
    $body = $this->actingAs($owner)->get('/app/sales/export.csv?filter[payment_status]=unpaid')->assertOk()->streamedContent();
    $lines = array_filter(explode("\n", trim($body)));
    expect($lines)->toHaveCount(3) // header + 1 sale + totals
        ->and($body)->toContain('2000.00')->toContain('Total');
});

it('lists each debtor once in the dues export with the credit limit and totals', function (): void {
    $body = $this->actingAs($this->world->user('Owner'))->get('/app/dues/export.csv')->assertOk()->streamedContent();
    expect(substr_count($body, 'রহিম স্টোর'))->toBe(1)
        ->and($body)->toContain('10000.00')->toContain('2000.00')->toContain('Total');
});

it('exports purchases of the current branch with the vendor name', function (): void {
    $vendor = Vendor::factory()->create(['tenant_id' => $this->world->tenant->id, 'name' => 'Dhaka Textiles']);
    Purchase::factory()->create(['tenant_id' => $this->world->tenant->id, 'branch_id' => $this->world->branch->id, 'vendor_id' => $vendor->id]);

    $body = $this->actingAs($this->world->user('Owner'))->get('/app/purchases/export.csv')->assertOk()->streamedContent();
    expect($body)->toContain('Dhaka Textiles');
});

it('never leaks another tenant\'s sales or dues into an export', function (): void {
    $other = app(TenantContext::class)->withoutTenancy(static fn (): SalesWorld => SalesWorld::make());
    app(TenantContext::class)->set($other->tenant, $other->branch);
    $product = $other->product(999_900, 1_000);
    $other->stock($product, 5, 1_000);
    $other->sell([$other->line($product, 1)], [], $other->customer(['name' => 'Foreign Customer']), soldAt: Carbon::parse('2026-11-03 06:00:00', 'UTC'));
    app(TenantContext::class)->set($this->world->tenant, $this->world->branch);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->world->tenant->id);

    $owner = $this->world->user('Owner');
    foreach (['sales', 'dues', 'returns', 'purchases'] as $list) {
        expect($this->actingAs($owner)->get("/app/{$list}/export.csv")->assertOk()->streamedContent())
            ->not->toContain('Foreign Customer')->not->toContain('9999.00');
    }
});
