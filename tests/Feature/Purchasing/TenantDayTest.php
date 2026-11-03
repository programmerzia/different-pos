<?php

declare(strict_types=1);

use App\Actions\Purchasing\ReceivePurchase;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CatalogWorld;
use Tests\Fixtures\CustomerWorld;

/*
| "Today", date filters and shown dates use the tenant's business day (Asia/Dhaka, UTC+6), not UTC.
| At 01:00 Dhaka on the 5th it is still 19:00 UTC on the 4th.
*/

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-11-05 01:00', 'Asia/Dhaka')));
afterEach(fn () => app(TenantContext::class)->forget());

it('lists, filters and shows a purchase received at 01:00 Dhaka under the local day', function (): void {
    $world = CatalogWorld::make();
    $world->enter();
    $vendor = $world->vendor();
    $purchase = app(ReceivePurchase::class)->handle($world->user, [
        'vendor_id' => $vendor->id,
        'items' => [['product_id' => $world->product()->id, 'qty' => Quantity::ofUnits(1), 'unit_cost' => Money::ofPaisa(10000)]],
    ]);
    expect($purchase->purchased_at->utc()->toDateTimeString())->toBe('2026-11-04 19:00:00');
    $world->user->refresh();

    $this->actingAs($world->user);
    $this->get('/app/purchases')->assertInertia(fn (Assert $p) => $p->where('rows.0.purchased_at', '2026-11-05'));
    $this->get('/app/purchases?filter[from]=2026-11-05&filter[to]=2026-11-05')->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('totals.bills', 1));
    $this->get('/app/purchases?filter[to]=2026-11-04')->assertInertia(fn (Assert $p) => $p->has('rows', 0)->where('totals.bills', 0));
    $this->get('/app/purchases?filter[from]=2026-11-06')->assertInertia(fn (Assert $p) => $p->has('rows', 0));
    $this->get("/app/purchases/{$purchase->uuid}")->assertInertia(fn (Assert $p) => $p->where('purchase.purchased_at', '2026-11-05'));
    $this->get('/app/purchases/create')->assertInertia(fn (Assert $p) => $p->where('today', '2026-11-05'));

    $this->get('/app/vendors')->assertInertia(fn (Assert $p) => $p->where('rows.0.uuid', $vendor->uuid)->where('rows.0.last_order', '2026-11-05'));
    $this->get('/app/vendors?filter[ordered]=today')->assertInertia(fn (Assert $p) => $p->has('rows', 1));
    $this->get("/app/vendors/{$vendor->uuid}")->assertInertia(fn (Assert $p) => $p->where('purchases.0.purchased_at', '2026-11-05'));
});

it('starts the customer "Today" preset at local midnight and exports the local purchase date', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    // 00:30 Dhaka on the 5th (today) and 23:00 Dhaka on the 4th (yesterday): both are the 4th in UTC.
    CustomerWorld::make($tenant, ['name' => 'Just after midnight', 'last_purchase_at' => CarbonImmutable::parse('2026-11-05 00:30', 'Asia/Dhaka')->utc()]);
    CustomerWorld::make($tenant, ['name' => 'Last night', 'last_purchase_at' => CarbonImmutable::parse('2026-11-04 23:00', 'Asia/Dhaka')->utc()]);
    CustomerWorld::make($tenant, ['name' => 'Last week', 'last_purchase_at' => CarbonImmutable::parse('2026-10-29 12:00', 'Asia/Dhaka')->utc()]);
    CustomerWorld::make($tenant, ['name' => 'Last year', 'last_purchase_at' => CarbonImmutable::parse('2025-12-31 23:30', 'Asia/Dhaka')->utc()]);

    $this->actingAs($user);
    $names = fn (string $preset): array => collect($this->get("/app/customers?filter[date]={$preset}")->viewData('page')['props']['customers'])->pluck('name')->sort()->values()->all();
    expect($names('today'))->toBe(['Just after midnight'])
        ->and($names('7d'))->toBe(['Just after midnight', 'Last night', 'Last week'])
        ->and($names('year'))->toBe(['Just after midnight', 'Last night', 'Last week']);

    $csv = $this->get('/app/customers/export.csv')->streamedContent();
    expect($csv)->toMatch('/Just after midnight.*,2026-11-05\r?\n/')->toMatch('/Last night.*,2026-11-04\r?\n/');
});

it('filters purchases by the Bangladesh week (Saturday start)', function (): void {
    $world = CatalogWorld::make();
    $world->enter();
    $receive = fn (string $at) => tap(app(ReceivePurchase::class)->handle($world->user, [
        'vendor_id' => $world->vendor()->id,
        'items' => [['product_id' => $world->product()->id, 'qty' => Quantity::ofUnits(1), 'unit_cost' => Money::ofPaisa(10000)]],
    ]), fn ($p) => $p->forceFill(['purchased_at' => CarbonImmutable::parse($at, 'Asia/Dhaka')->utc()])->saveQuietly());
    // 5 Nov 2026 is a Thursday: the week began on Saturday 31 Oct.
    $in = $receive('2026-10-31 10:00');
    $receive('2026-10-30 22:00');

    $this->actingAs($world->user)->get('/app/purchases?filter[period]=week')->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.number', $in->number));
});
