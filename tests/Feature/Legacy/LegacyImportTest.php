<?php

declare(strict_types=1);

use App\Enums\SaleOrigin;
use App\Legacy\DryRunRollback;
use App\Legacy\Importer;
use App\Legacy\ImportOptions;
use App\Legacy\ImportResult;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Customers\CustomerNumber;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\LegacyFixture;

beforeEach(function (): void {
    LegacyFixture::install();
    $this->reportDir = sys_get_temp_dir().'/legacy-import-test-'.getmypid().'-'.uniqid();
    $this->import = fn (bool $dryRun = false): ImportResult => app(Importer::class)->run(
        new ImportOptions(LegacyFixture::CONNECTION, [7, 10], 'different-fashion', $dryRun, $this->reportDir),
    );
    $this->tenant = fn (): Tenant => Tenant::query()->where('slug', 'different-fashion')->firstOrFail();
    $this->as = fn (callable $fn): mixed => app(TenantContext::class)->run(($this->tenant)(), null, $fn);
    $this->counts = function (): array {
        $tenantId = ($this->tenant)()->id;
        $counts = [];
        foreach (['branches', 'registers', 'users', 'brands', 'categories', 'vendors', 'customer_groups', 'customers', 'units', 'products', 'purchases', 'stock_batches', 'stock_movements', 'branch_stocks', 'sales', 'payments', 'sale_returns'] as $table) {
            $counts[$table] = DB::table($table)->where('tenant_id', $tenantId)->count();
        }
        $sales = DB::table('sales')->where('tenant_id', $tenantId)->pluck('id');
        $counts['sale_items'] = DB::table('sale_items')->whereIn('sale_id', $sales)->count();
        $counts['payment_allocations'] = DB::table('payment_allocations')->where('allocatable_type', 'sale')->whereIn('allocatable_id', $sales)->count();
        $counts['legacy_id_maps'] = DB::table('legacy_id_maps')->count();

        return $counts;
    };
    $this->report = fn (string $kind): array => array_values(array_filter(
        array_map(str_getcsv(...), array_slice(file($this->reportDir.'/migration_report.csv', FILE_IGNORE_NEW_LINES) ?: [], 1)),
        fn (array $line): bool => $line[0] === $kind,
    ));
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->reportDir.'/*') ?: []);
    @rmdir($this->reportDir);
});

it('imports stores 7 and 10 into one tenant with two branches', function (): void {
    ($this->import)();

    $tenant = ($this->tenant)();
    expect($tenant->name)->toBe('DIFFERENT FASHION')
        ->and(DB::table('branches')->where('tenant_id', $tenant->id)->orderBy('code')->pluck('name', 'code')->all())->toBe(['DF1' => 'Main', 'DF2' => 'Branch 2'])
        ->and(DB::table('registers')->where('tenant_id', $tenant->id)->pluck('code')->all())->toBe(['R1', 'R1']);

    expect(($this->counts)())->toMatchArray([
        'users' => 2, 'brands' => 1, 'categories' => 2, 'vendors' => 3, 'customer_groups' => 1,
        'customers' => 4, // 815, 816, the merged 830 shell, walk-in
        'products' => 3, 'purchases' => 2, 'stock_batches' => 3, 'stock_movements' => 3, 'branch_stocks' => 3,
        'sales' => 3, 'sale_items' => 3, 'payments' => 3, 'payment_allocations' => 3, 'sale_returns' => 1,
    ]);

    // Store 99 is not part of the run.
    expect(DB::table('customers')->where('legacy_id', 900)->exists())->toBeFalse()
        ->and(User::query()->where('legacy_id', 40)->exists())->toBeFalse();
});

it('is idempotent: a second run creates nothing new', function (): void {
    ($this->import)();
    $first = ($this->counts)();
    $counter = ($this->tenant)()->sync_counter;

    $result = ($this->import)();

    expect(($this->counts)())->toBe($first)
        ->and(($this->tenant)()->sync_counter)->toBe($counter)
        ->and(collect($result->steps)->except('Roles')->sum('created'))->toBe(0);
});

it('suffixes duplicate product codes and reports them and cost-0 products', function (): void {
    ($this->import)();

    $skus = ($this->as)(fn (): array => Product::query()->orderBy('legacy_id')->pluck('sku', 'legacy_id')->all());
    expect($skus)->toBe([2418 => '410500', 2419 => '309000', 2451 => '410500-2451'])
        ->and(($this->report)('duplicate_sku'))->toHaveCount(1)
        ->and(($this->report)('duplicate_sku')[0][2])->toBe('2451')
        ->and(array_column(($this->report)('cost_zero'), 2))->toBe(['2418']);

    $panjabi = ($this->as)(fn (): Product => Product::query()->where('legacy_id', 2419)->firstOrFail());
    expect($panjabi->cost_price->paisa)->toBe(70050)
        ->and($panjabi->sale_price->paisa)->toBe(90000)
        ->and($panjabi->is_active)->toBeFalse();
});

it('numbers customers in ascending legacy id order with valid Luhn numbers', function (): void {
    ($this->import)();

    $numbers = ($this->as)(fn (): array => Customer::query()->whereNotNull('legacy_id')->whereNull('merged_into_id')
        ->orderBy('legacy_id')->pluck('customer_no', 'legacy_id')->map(fn (mixed $no): string => (string) $no)->all());

    expect($numbers)->toBe([815 => '10000016', 816 => '10000024']);
    foreach ($numbers as $number) {
        expect(CustomerNumber::isValid($number))->toBeTrue();
    }

    $walkIn = ($this->as)(fn (): Customer => Customer::query()->where('is_walk_in', true)->sole());
    expect(CustomerNumber::isValid((string) $walkIn->customer_no))->toBeTrue()
        ->and((int) $walkIn->customer_no)->toBeGreaterThan(10000024);

    $karim = ($this->as)(fn (): Customer => Customer::query()->where('legacy_id', 816)->firstOrFail());
    expect($karim->name)->toBe('Karim Traders')
        ->and($karim->phone)->toBeNull()
        ->and(($this->report)('invalid_phone'))->toHaveCount(2); // customer 816 and vendor 3
});

it('merges a duplicate phone into the oldest customer and remaps its sales', function (): void {
    ($this->import)();

    [$rahim, $shell] = ($this->as)(fn (): array => [
        Customer::query()->where('legacy_id', 815)->firstOrFail(),
        Customer::query()->withTrashed()->where('legacy_id', 830)->firstOrFail(),
    ]);

    expect($rahim->phone)->toBe('+8801711000001')
        ->and($shell->trashed())->toBeTrue()
        ->and($shell->merged_into_id)->toBe($rahim->id)
        ->and($shell->customer_no)->toBeNull()
        ->and(DB::table('sales')->where('legacy_id', 1270)->value('customer_id'))->toBe($rahim->id)
        ->and($rahim->balance->paisa)->toBe(170000)
        ->and(($this->report)('merged_duplicate_phone'))->toHaveCount(1);
});

it('builds stock from batch remainders and reports the difference from product quantity', function (): void {
    ($this->import)();
    $tenantId = ($this->tenant)()->id;

    $onHand = DB::table('branch_stocks')->where('tenant_id', $tenantId)->sum('qty_on_hand');
    $batches = DB::table('stock_batches')->where('tenant_id', $tenantId)->sum('qty_remaining');
    $movements = DB::table('stock_movements')->where('tenant_id', $tenantId)->where('type', 'opening')->sum('qty');

    expect((float) $onHand)->toBe(16.0)
        ->and((string) $batches)->toBe((string) $onHand)
        ->and((string) $movements)->toBe((string) $onHand)
        ->and(DB::table('stock_batches')->where('legacy_id', 5001)->first(['qty_received', 'qty_remaining', 'unit_cost', 'batch_no']))
        ->toEqual((object) ['qty_received' => '20.000', 'qty_remaining' => '8.000', 'unit_cost' => 60000, 'batch_no' => 'ST1']);

    $mismatch = ($this->report)('stock_mismatch');
    expect($mismatch)->toHaveCount(1)
        ->and($mismatch[0][2])->toBe('2418')
        ->and($mismatch[0][3])->toContain('difference -2');

    // The second ST1 (store 10) collides and gets the branch prefix.
    expect(DB::table('purchases')->where('tenant_id', $tenantId)->orderBy('legacy_id')->pluck('number')->all())->toBe(['ST1', 'DF2-ST1']);
});

it('imports sales as completed history with lines, batch links and cogs', function (): void {
    ($this->import)();

    $sale = DB::table('sales')->where('legacy_id', 1269)->first();
    expect($sale->origin)->toBe(SaleOrigin::Import->value)
        ->and($sale->status)->toBe('completed')
        ->and($sale->member_auth)->toBe('none')
        ->and($sale->number)->toBe('S-1')
        ->and((int) $sale->subtotal)->toBe(210000)
        ->and((int) $sale->item_discount_total)->toBe(10000)
        ->and((int) $sale->total)->toBe(200000)
        ->and((int) $sale->cogs_total)->toBe(120000)
        ->and($sale->courier_name)->toBeNull()
        ->and((int) $sale->returned_total)->toBe(100000)
        ->and($sale->sold_at)->toBe('2024-05-06 05:00:45'); // Asia/Dhaka 11:00:45 -> UTC

    $item = DB::table('sale_items')->where('legacy_id', 9001)->first();
    expect((int) $item->line_total)->toBe(200000)
        ->and($item->qty_returned)->toBe('1.000')
        ->and(DB::table('sale_item_batches')->where('sale_item_id', $item->id)->value('unit_cost'))->toBe(60000);

    // Line without a stock invoice: no batch link, reported.
    $unlinked = DB::table('sale_items')->where('legacy_id', 9002)->value('id');
    expect(DB::table('sale_item_batches')->where('sale_item_id', $unlinked)->exists())->toBeFalse()
        ->and(array_column(($this->report)('unlinked_batch'), 2))->toBe(['9002']);

    // Store 10's S-1 collides with store 7's and gets the branch prefix; customer 0 -> walk-in.
    $walkIn = ($this->as)(fn (): int => Customer::query()->where('is_walk_in', true)->value('id'));
    expect(DB::table('sales')->where('legacy_id', 1271)->first(['number', 'customer_id']))
        ->toEqual((object) ['number' => 'DF2-S-1', 'customer_id' => $walkIn]);
});

it('reports a future-dated sale and imports it unchanged', function (): void {
    ($this->import)();

    expect(DB::table('sales')->where('legacy_id', 1271)->value('sold_at'))->toBe('2027-03-01 18:00:00')
        ->and(($this->report)('future_dated'))->toHaveCount(1)
        ->and(($this->report)('future_dated')[0][2])->toBe('1271');
});

it('imports payment rows and synthesises payments for paid invoices without them', function (): void {
    ($this->import)();

    $payments = DB::table('payments')->where('tenant_id', ($this->tenant)()->id)->orderBy('number')->get(['number', 'amount', 'paid_at']);
    expect($payments->pluck('amount', 'number')->all())->toBe(['L-P7001' => 100000, 'L-S1269' => 200000, 'L-S1271' => 105000])
        ->and($payments->firstWhere('number', 'L-S1269')->paid_at)->toBe('2024-05-06 05:00:45');

    foreach ([1269 => [200000, 0, 'paid'], 1270 => [100000, 170000, 'partial'], 1271 => [105000, 0, 'paid']] as $legacyId => [$paid, $due, $status]) {
        $sale = DB::table('sales')->where('legacy_id', $legacyId)->first(['id', 'paid', 'due', 'payment_status']);
        expect([(int) $sale->paid, (int) $sale->due, $sale->payment_status])->toBe([$paid, $due, $status])
            ->and((int) DB::table('payment_allocations')->where('allocatable_type', 'sale')->where('allocatable_id', $sale->id)->sum('amount'))->toBe($paid);
    }

    expect(($this->report)('synthesised_payment'))->toHaveCount(1)
        ->and(($this->report)('synthesised_payment')[0][3])->toStartWith('2 paid invoices')
        ->and(array_column(($this->report)('payment_not_positive'), 2))->toBe(['7002']);
});

it('imports users with the legacy hash only and a forced password change', function (): void {
    ($this->import)();

    $users = ($this->as)(fn (): array => User::inCurrentTenant()->orderBy('legacy_id')->get()->all());
    expect($users)->toHaveCount(2);
    [$one, $two] = $users;

    expect($one->username)->toBe('Different -1')
        ->and($one->email)->toBeNull()
        ->and($one->phone)->toBe('+8801675111376')
        ->and($one->legacy_password_md5)->toBe(md5('secret'))
        ->and($one->must_change_password)->toBeTrue()
        ->and(Hash::check('secret', $one->password))->toBeFalse()
        ->and($two->phone)->toBeNull()
        ->and($two->is_active)->toBeFalse()
        ->and(DB::table('branch_user')->where('user_id', $one->id)->value('is_default'))->toBe(1);

    app(PermissionRegistrar::class)->setPermissionsTeamId(($this->tenant)()->id);
    expect($one->fresh()?->hasRole('Manager'))->toBeTrue();
});

it('writes a reconciliation with no unexplained differences', function (): void {
    $result = ($this->import)();

    $differences = collect($result->reconciliation)->reject(fn (array $row): bool => in_array((string) $row['diff'], ['0', '0.00'], true));
    expect($differences->pluck('entity', 'store')->all())->toBe([7 => 'stock_units.products_vs_on_hand'])
        ->and(file_exists($result->reconciliationPath))->toBeTrue()
        ->and(file($result->reconciliationPath)[0] ?? '')->toStartWith('store,entity,legacy_count,new_count,legacy_sum,new_sum,diff');
});

it('leaves nothing behind on a dry run but still writes the reports', function (): void {
    $before = DB::table('legacy_id_maps')->count();

    try {
        ($this->import)(true);
        $this->fail('Expected a DryRunRollback.');
    } catch (DryRunRollback $rollback) {
        expect($rollback->result->dryRun)->toBeTrue()
            ->and($rollback->result->steps['Sales']['created'])->toBe(3);
    }

    expect(Tenant::query()->where('slug', 'different-fashion')->exists())->toBeFalse()
        ->and(DB::table('legacy_id_maps')->count())->toBe($before)
        ->and(DB::table('sales')->where('origin', 'import')->count())->toBe(0)
        ->and(file_exists($this->reportDir.'/migration_report.csv'))->toBeTrue();
});

it('keeps imported rows invisible to another tenant', function (): void {
    ($this->import)();
    $other = Tenant::factory()->create();
    $otherBranch = makeBranch($other);

    $visible = app(TenantContext::class)->run($other, $otherBranch, fn (): array => [
        Customer::query()->withTrashed()->count(),
        Product::query()->withTrashed()->count(),
        Sale::query()->count(),
        Payment::query()->count(),
        StockBatch::query()->count(),
        Vendor::query()->count(),
        User::inCurrentTenant()->count(),
    ]);

    expect($visible)->toBe([0, 0, 0, 0, 0, 0, 0]);
});

it('runs from the artisan command and prints the summary', function (): void {
    $this->artisan('legacy:import', ['--connection' => LegacyFixture::CONNECTION, '--stores' => '7,10', '--tenant' => 'different-fashion', '--dry-run' => true, '--report-dir' => $this->reportDir])
        ->expectsOutputToContain('Dry run finished and rolled back')
        ->assertSuccessful();

    expect(Tenant::query()->where('slug', 'different-fashion')->exists())->toBeFalse();
});

it('rejects a missing store or tenant option', function (): void {
    $this->artisan('legacy:import', ['--stores' => '', '--tenant' => 'different-fashion'])->assertFailed();
});

it('fails for a legacy store that does not exist and leaves nothing', function (): void {
    expect(fn (): ImportResult => app(Importer::class)->run(new ImportOptions(LegacyFixture::CONNECTION, [7, 12345], 'different-fashion', false, $this->reportDir)))
        ->toThrow(RuntimeException::class, 'Legacy store 12345 does not exist.');

    expect(Tenant::query()->where('slug', 'different-fashion')->exists())->toBeFalse();
});
