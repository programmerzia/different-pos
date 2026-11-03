<?php

declare(strict_types=1);

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Vendor;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

it('adds hub, open bills, last order, payable totals and the hub chips to the vendor directory', function (): void {
    $world = CatalogWorld::make();
    $other = CatalogWorld::make();
    app(TenantContext::class)->withoutTenancy(function () use ($world, $other): void {
        $alpha = Vendor::factory()->create(['tenant_id' => $world->tenant->id, 'name' => 'Alpha', 'address' => 'House 4, Road 2, Narayanganj', 'balance' => Money::ofPaisa(30000)]);
        Vendor::factory()->create(['tenant_id' => $world->tenant->id, 'name' => 'Beta', 'address' => 'Dhaka', 'balance' => Money::ofPaisa(0)]);
        Vendor::factory()->create(['tenant_id' => $other->tenant->id, 'name' => 'Foreign', 'address' => 'Chittagong', 'balance' => Money::ofPaisa(99999)]);
        foreach ([[30000, '2026-10-01'], [0, '2026-10-05']] as [$due, $day]) {
            Purchase::factory()->create([
                'tenant_id' => $world->tenant->id, 'branch_id' => $world->branch->id, 'vendor_id' => $alpha->id,
                'status' => PurchaseStatus::Received, 'purchased_at' => $day, 'total' => Money::ofPaisa(30000), 'due' => Money::ofPaisa($due),
            ]);
        }
    });

    $this->actingAs($world->user)->get('/app/vendors')->assertInertia(fn (Assert $page) => $page
        ->component('vendors/index')
        ->has('rows', 2)
        ->where('rows.0.name', 'Alpha')
        ->where('rows.0.hub', 'Narayanganj')
        ->where('rows.0.open_bills', 1)
        ->where('rows.0.last_order', '2026-10-05')
        ->where('rows.1.open_bills', 0)
        ->where('totals.payable', 30000)
        ->where('totals.open_bills', 1)
        ->where('hubs', fn ($hubs) => collect($hubs)->sort()->values()->all() === ['Dhaka', 'Narayanganj'])
        ->where('canPay', true)
        ->has('paymentMethods'));

    $this->actingAs($world->user)->get('/app/vendors?filter[hub]=Dhaka')->assertInertia(fn (Assert $page) => $page
        ->has('rows', 1)->where('rows.0.name', 'Beta')->where('totals.payable', 0));
    $this->actingAs($world->user)->get('/app/vendors?filter[status]=cleared')->assertInertia(fn (Assert $page) => $page
        ->has('rows', 1)->where('rows.0.name', 'Beta'));
});

it('filters the directory by the date of the last received order', function (): void {
    $world = CatalogWorld::make();
    $other = CatalogWorld::make();
    app(TenantContext::class)->withoutTenancy(function () use ($world, $other): void {
        $recent = Vendor::factory()->create(['tenant_id' => $world->tenant->id, 'name' => 'Recent']);
        $old = Vendor::factory()->create(['tenant_id' => $world->tenant->id, 'name' => 'Old']);
        Vendor::factory()->create(['tenant_id' => $world->tenant->id, 'name' => 'Never']);
        $foreign = Vendor::factory()->create(['tenant_id' => $other->tenant->id, 'name' => 'Foreign']);
        foreach ([[$world, $recent, now()->subDays(2)], [$world, $old, now()->subDays(120)], [$other, $foreign, now()]] as [$w, $vendor, $at]) {
            Purchase::factory()->create([
                'tenant_id' => $w->tenant->id, 'branch_id' => $w->branch->id, 'vendor_id' => $vendor->id,
                'status' => PurchaseStatus::Received, 'purchased_at' => $at,
            ]);
        }
        // A draft order never counts as an order.
        Purchase::factory()->create([
            'tenant_id' => $world->tenant->id, 'branch_id' => $world->branch->id, 'vendor_id' => $old->id,
            'status' => PurchaseStatus::Draft, 'purchased_at' => now(),
        ]);
    });
    $names = fn (string $filter): array => $this->actingAs($world->user)->get('/app/vendors?filter[ordered]='.$filter)
        ->viewData('page')['props']['rows'];

    expect(array_column($names('7d'), 'name'))->toBe(['Recent'])
        ->and(array_column($names('today'), 'name'))->toBe([])
        ->and(array_column($names('idle_90d'), 'name'))->toBe(['Never', 'Old'])
        ->and(array_column($names('bogus'), 'name'))->toBe(['Never', 'Old', 'Recent']);
});
