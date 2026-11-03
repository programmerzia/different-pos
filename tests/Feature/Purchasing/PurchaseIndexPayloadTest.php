<?php

declare(strict_types=1);

use App\Enums\PurchaseStatus;
use App\Models\Branch;
use App\Models\Purchase;
use App\Support\Money\Money;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

function purchaseIn(CatalogWorld $world, Branch $branch, int $total, int $paid): Purchase
{
    return app(TenantContext::class)->withoutTenancy(fn (): Purchase => Purchase::factory()->create([
        'tenant_id' => $world->tenant->id,
        'branch_id' => $branch->id,
        'vendor_id' => $world->vendor()->id,
        'status' => PurchaseStatus::Received,
        'total' => Money::ofPaisa($total),
        'paid' => Money::ofPaisa($paid),
        'due' => Money::ofPaisa($total - $paid),
        'vendor_ref' => 'BILL-'.$total,
    ]));
}

it('adds bill ref, paid, qty and the KPI totals to the purchases list', function (): void {
    $world = CatalogWorld::make();
    purchaseIn($world, $world->branch, 100000, 40000);
    purchaseIn($world, $world->branch, 50000, 50000);

    $this->actingAs($world->user)->get('/app/purchases')->assertInertia(fn (Assert $page) => $page
        ->component('purchases/index')
        ->has('rows', 2)
        ->where('totals.bills', 2)
        ->where('totals.total', 150000)
        ->where('totals.paid', 90000)
        ->where('totals.due', 60000)
        ->where('rows.0.current_branch', true)
        ->has('rows.0', fn (Assert $row) => $row->has('vendor_ref')->has('paid')->has('qty')->has('items')->has('branch')->etc()));
});

it('lets an all-branches user view every branch, and nobody else', function (): void {
    $world = CatalogWorld::make();
    $second = app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->for($world->tenant)->create(['is_active' => true]));
    purchaseIn($world, $world->branch, 100000, 0);
    purchaseIn($world, $second, 70000, 0);

    $this->actingAs($world->user)->get('/app/purchases')->assertInertia(fn (Assert $page) => $page
        ->has('rows', 1)->has('branches', 2));
    $this->actingAs($world->user)->get('/app/purchases?filter[branch]=all')->assertInertia(fn (Assert $page) => $page
        ->has('rows', 2)->where('totals.total', 170000));
    $this->actingAs($world->user)->get('/app/purchases?filter[branch]='.$second->id)->assertInertia(fn (Assert $page) => $page
        ->has('rows', 1)->where('rows.0.current_branch', false));

    $keeper = CatalogWorld::make(SystemRoles::STOCK_KEEPER);
    $keeperSecond = app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->for($keeper->tenant)->create());
    purchaseIn($keeper, $keeper->branch, 1000, 0);
    purchaseIn($keeper, $keeperSecond, 2000, 0);
    $this->actingAs($keeper->user)->get('/app/purchases?filter[branch]=all')->assertInertia(fn (Assert $page) => $page
        ->has('rows', 1)->where('branches', []));
});

it('never leaks another tenant\'s purchases into the totals or the branch filter', function (): void {
    $mine = CatalogWorld::make();
    $other = CatalogWorld::make();
    purchaseIn($mine, $mine->branch, 1000, 0);
    purchaseIn($other, $other->branch, 99000, 0);

    $this->actingAs($mine->user)->get('/app/purchases?filter[branch]=all')->assertInertia(fn (Assert $page) => $page
        ->has('rows', 1)->where('totals.total', 1000));
    $this->actingAs($mine->user)->get('/app/purchases?filter[branch]='.$other->branch->id)->assertNotFound();
});
