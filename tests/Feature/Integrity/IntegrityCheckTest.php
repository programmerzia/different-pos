<?php

declare(strict_types=1);

use App\Actions\Sales\CollectCustomerPayment;
use App\Actions\Sales\CreateSaleReturn;
use App\Actions\Sales\Data\CustomerPaymentDraft;
use App\Actions\Sales\Data\ReturnDraft;
use App\Actions\Sales\Data\ReturnLineDraft;
use App\Enums\RefundMode;
use App\Models\MembershipCard;
use App\Models\Vendor;
use App\Support\Customers\CustomerNumber;
use App\Support\Integrity\CheckResult;
use App\Support\Integrity\CheckStatus;
use App\Support\Integrity\IntegrityChecker;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\SalesWorld;

/** A few cash/credit sales, a payment collection and a refunded return, so every check has rows to look at. */
beforeEach(function (): void {
    $this->seedWorld = function (SalesWorld $world): array {
        $shirt = $world->product(150_000, 60_000);
        $world->stock($shirt, 10, 60_000);
        $customer = $world->customer(['customer_no' => (int) CustomerNumber::generate(1_000_001)]);

        $cash = $world->sell([$world->line($shirt, 2)], [$world->tender('cash', 300_000)]);
        $world->sell([$world->line($shirt, 1)], [$world->tender('cash', 50_000)], $customer);
        app(CollectCustomerPayment::class)->handle($world->user('Cashier'), new CustomerPaymentDraft(
            $customer->id, Money::ofPaisa(30_000), $world->methods['cash']->id,
        ));
        app(CreateSaleReturn::class)->handle($world->user('Manager'), new ReturnDraft(
            $cash->id, [new ReturnLineDraft($cash->items[0]->id, Quantity::ofUnits(1), true)], RefundMode::Refund, 'size',
        ));
        MembershipCard::factory()->create(['tenant_id' => $world->tenant->id, 'customer_id' => $customer->id]);

        return [$shirt, $customer, $cash];
    };

    $this->world = SalesWorld::make();
    [$this->shirt, $this->customer, $this->cash] = ($this->seedWorld)($this->world);

    /** @return array<string, CheckResult> */
    $this->run = fn (?SalesWorld $world = null): array => collect(app(IntegrityChecker::class)->run(($world ?? $this->world)->tenant))
        ->keyBy(fn (CheckResult $result): string => $result->name)->all();
    $this->status = fn (string $name, ?SalesWorld $world = null): CheckStatus => ($this->run)($world)[$name]->status;
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('is green on a world with sales, a collection and a return', function (): void {
    $results = ($this->run)();

    expect($results)->toHaveCount(11);
    foreach ($results as $result) {
        expect($result->status)->toBe(CheckStatus::Ok, $result->name.' '.json_encode($result->sample))
            ->and($result->count)->toBe(0);
    }
    $this->artisan('integrity:check', ['--tenant' => $this->world->tenant->slug])->assertExitCode(0);
});

it('flags a sale whose total no longer matches its lines', function (): void {
    DB::table('sales')->where('id', $this->cash->id)->update(['rounding' => 5]);

    $result = ($this->run)()['sale_totals'];
    expect($result->status)->toBe(CheckStatus::Fail)->and($result->sample)->toBe([$this->cash->id]);
});

it('flags a completed sale whose paid does not match its allocations', function (): void {
    DB::table('sales')->where('id', $this->cash->id)->update(['paid' => DB::raw('paid - 1'), 'due' => DB::raw('due + 1')]);

    expect(($this->run)()['sale_payments']->sample)->toBe([$this->cash->id]);
});

it('flags a sale whose due is not total minus paid', function (): void {
    DB::table('sales')->where('id', $this->cash->id)->update(['due' => 7]);

    expect(($this->status)('sale_payments'))->toBe(CheckStatus::Fail);
});

it('flags a sale line whose cogs differs from its batches', function (): void {
    DB::table('sale_items')->where('sale_id', $this->cash->id)->update(['cogs' => DB::raw('cogs + 1')]);

    expect(($this->status)('sale_cogs'))->toBe(CheckStatus::Fail);
});

it('flags a native sale line that lost its batch link but only warns for an imported one', function (): void {
    $item = $this->cash->items[0];
    DB::table('sale_item_batches')->where('sale_item_id', $item->id)->delete();

    $result = ($this->run)()['sale_cogs'];
    expect($result->status)->toBe(CheckStatus::Fail)->and($result->sample)->toBe([$item->id]);

    DB::table('sales')->where('id', $this->cash->id)->update(['origin' => 'import']);
    $result = ($this->run)()['sale_cogs'];
    expect($result->status)->toBe(CheckStatus::Warn)->and($result->count)->toBe(1);
    $this->artisan('integrity:check', ['--tenant' => $this->world->tenant->slug])->assertExitCode(0);
});

it('flags a batch row that sells more than the line', function (): void {
    DB::table('sale_item_batches')->where('sale_item_id', $this->cash->items[0]->id)->update(['qty' => 5]);

    expect(($this->status)('sale_cogs'))->toBe(CheckStatus::Fail);
});

it('flags stock on hand that differs from the movements', function (): void {
    DB::table('stock_movements')->where('product_id', $this->shirt->id)->orderBy('id')->limit(1)->update(['qty' => DB::raw('qty + 1')]);

    $results = ($this->run)();
    expect($results['stock_vs_movements']->status)->toBe(CheckStatus::Fail)
        ->and($results['stock_vs_movements']->sample)->toBe([$this->world->branch->id.':'.$this->shirt->id])
        ->and($results['stock_vs_batches']->status)->toBe(CheckStatus::Ok);
});

it('flags stock on hand that differs from the batch remainders', function (): void {
    DB::table('stock_batches')->where('product_id', $this->shirt->id)->update(['qty_remaining' => DB::raw('qty_remaining + 1')]);

    $results = ($this->run)();
    expect($results['stock_vs_batches']->status)->toBe(CheckStatus::Fail)
        ->and($results['stock_vs_movements']->status)->toBe(CheckStatus::Ok);
});

it('flags a customer balance that differs from sales, credits and receipts', function (): void {
    DB::table('customers')->where('id', $this->customer->id)->update(['balance' => DB::raw('balance + 1')]);

    expect(($this->run)()['customer_balances']->sample)->toBe([$this->customer->id]);
});

it('flags a vendor balance that differs from its dues', function (): void {
    $vendor = Vendor::factory()->create(['tenant_id' => $this->world->tenant->id]);
    expect(($this->status)('vendor_balances'))->toBe(CheckStatus::Ok);

    DB::table('vendors')->where('id', $vendor->id)->update(['balance' => 500]);

    expect(($this->run)()['vendor_balances']->sample)->toBe([$vendor->id]);
});

// Two active cards for one customer cannot be inserted (unique generated active_guard), so the failing
// state is unreachable here; the check is the nightly backstop should that index ever go missing.
it('sees the one active card of a customer as fine', function (): void {
    expect(DB::table('membership_cards')->where('status', 'active')->count())->toBe(1)
        ->and(($this->status)('one_active_card'))->toBe(CheckStatus::Ok);
});

it('flags a customer number that fails the Luhn check', function (): void {
    DB::table('customers')->where('id', $this->customer->id)->update(['customer_no' => 10_000_010]);

    $result = ($this->run)()['customer_numbers'];
    expect($result->status)->toBe(CheckStatus::Fail)->and($result->sample)->toBe([$this->customer->id]);
});

it('flags a syncable row without a sync_version', function (): void {
    DB::table('products')->where('id', $this->shirt->id)->update(['sync_version' => null]);

    $result = ($this->run)()['sync_versions'];
    expect($result->status)->toBe(CheckStatus::Fail)->and($result->sample)->toBe(['products:'.$this->shirt->id]);
});

it('flags a document sequence that is behind the numbers already used', function (): void {
    DB::table('document_sequences')->where('tenant_id', $this->world->tenant->id)->where('type', 'sale')->update(['next_number' => 1]);

    $result = ($this->run)()['document_sequences'];
    expect($result->status)->toBe(CheckStatus::Fail)->and($result->sample)->toBe(['sale@'.$this->world->branch->id]);
});

it('flags a customer sequence that is behind an issued customer number', function (): void {
    DB::table('document_sequences')->updateOrInsert(
        ['tenant_id' => $this->world->tenant->id, 'type' => 'customer', 'branch_id' => null],
        ['next_number' => 1, 'pad' => 7, 'reset_yearly' => false, 'prefix' => ''],
    );
    DB::table('customers')->where('id', $this->customer->id)->update(['customer_no' => (int) CustomerNumber::generate(1_000_050)]);

    expect(($this->run)()['document_sequences']->sample)->toBe(['customer@-']);
});

it('does not let corruption in another tenant fail this tenant', function (): void {
    $other = SalesWorld::make();
    [, $otherCustomer, $otherSale] = ($this->seedWorld)($other);
    DB::table('customers')->where('id', $otherCustomer->id)->update(['balance' => 99]);
    DB::table('sales')->where('id', $otherSale->id)->update(['rounding' => 3]);
    DB::table('products')->where('tenant_id', $other->tenant->id)->update(['sync_version' => null]);

    foreach (($this->run)() as $result) {
        expect($result->status)->toBe(CheckStatus::Ok, $result->name);
    }
    $broken = ($this->run)($other);
    expect($broken['customer_balances']->status)->toBe(CheckStatus::Fail)
        ->and($broken['sale_totals']->status)->toBe(CheckStatus::Fail)
        ->and($broken['sync_versions']->status)->toBe(CheckStatus::Fail);

    $this->artisan('integrity:check', ['--tenant' => $this->world->tenant->slug])->assertExitCode(0);
    $this->artisan('integrity:check', ['--tenant' => $other->tenant->slug])->assertExitCode(1);
});

it('prints a JSON report and exits 1 when a check fails', function (): void {
    DB::table('customers')->where('id', $this->customer->id)->update(['balance' => DB::raw('balance + 1')]);

    $exit = Artisan::call('integrity:check', ['--tenant' => $this->world->tenant->slug, '--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exit)->toBe(1)
        ->and($report['ok'])->toBeFalse()
        ->and($report['tenants'])->toHaveCount(1)
        ->and($report['tenants'][0]['tenant'])->toBe($this->world->tenant->slug)
        ->and($report['tenants'][0]['ok'])->toBeFalse()
        ->and($report['tenants'][0]['checks'])->toHaveCount(11);

    $balances = collect($report['tenants'][0]['checks'])->firstWhere('name', 'customer_balances');
    expect($balances)->toBe(['name' => 'customer_balances', 'status' => 'fail', 'count' => 1, 'sample' => [$this->customer->id]]);
});

it('checks every tenant without --tenant and rejects an unknown slug', function (): void {
    SalesWorld::make();

    Artisan::call('integrity:check', ['--json' => true]);
    expect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['tenants'])->toHaveCount(2);

    $this->artisan('integrity:check', ['--tenant' => 'nope'])->assertExitCode(1);
});
