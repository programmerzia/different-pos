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
