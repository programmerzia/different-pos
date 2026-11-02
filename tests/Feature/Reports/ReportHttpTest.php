<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\User;
use App\Reports\DashboardQuery;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-03 08:00:00', 'UTC'));
    $this->world = SalesWorld::make();
    $this->product = $this->world->product(100_000, 60_000, ['reorder_level' => Quantity::ofUnits(10)]);
    $this->world->stock($this->product, 5, 60_000);
    $this->customer = $this->world->customer(['name' => 'Alice']);
    $this->world->sell([$this->world->line($this->product, 2)], [$this->world->tender('cash', 100_000)], $this->customer, soldAt: Carbon::parse('2026-11-03 06:00:00', 'UTC'));
    foreach ($this->world->users as $user) {
        $user->branches()->attach($this->world->branch);
    }
    $this->second = Branch::factory()->for($this->world->tenant)->create(['code' => 'DF2']);
    $this->world->users['Owner']->branches()->attach($this->second);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->world->tenant->id);
    $this->withSession(['branch_id' => $this->world->branch->id]);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('shows the catalogue and a report to a permitted user', function (): void {
    $this->actingAs($this->world->user('Accountant'))->get(route('reports.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('reports/index')->has('groups.sales'));

    $this->actingAs($this->world->user('Accountant'))
        ->get(route('reports.show', ['report' => 'top-customers', 'from' => '2026-11-01', 'to' => '2026-11-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('reports/show')
            ->where('result.rows.0.customer', 'Alice')
            ->where('result.totals.net', 200_000));
});

it('forbids reports without reports.view and exports without reports.export', function (): void {
    $this->actingAs($this->world->user('Cashier'))->get(route('reports.index'))->assertForbidden();
    $this->actingAs($this->world->user('Cashier'))->get(route('reports.show', 'sales-by-day'))->assertForbidden();
    // Stock Keeper has reports.view only
    $this->actingAs($this->world->user('Stock Keeper'))->get(route('reports.show', 'stock-valuation'))->assertOk();
    $this->actingAs($this->world->user('Stock Keeper'))->post(route('reports.export', 'stock-valuation'))->assertForbidden();
});

it('returns 404 for an unknown report', function (): void {
    $this->actingAs($this->world->user('Owner'))->get(route('reports.show', 'nope'))->assertNotFound();
});

it('hides profit columns without reports.profit', function (): void {
    $stockKeeper = $this->world->user('Stock Keeper');
    $this->actingAs($stockKeeper)->get(route('reports.show', 'stock-valuation'))
        ->assertInertia(fn ($page) => $page->where('result.columns', fn ($columns) => ! collect($columns)->contains('key', 'cost_value')));

    $this->actingAs($this->world->user('Owner'))->get(route('reports.show', 'stock-valuation'))
        ->assertInertia(fn ($page) => $page->where('result.columns', fn ($columns) => collect($columns)->contains('key', 'cost_value')));
});

it('limits a user without reports.all_branches to the current branch', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->world->tenant->id]);
    $user->branches()->attach($this->world->branch);
    $user->givePermissionTo('reports.view');

    $this->actingAs($user)
        ->get(route('reports.show', ['report' => 'sales-by-day', 'from' => '2026-11-03', 'to' => '2026-11-03', 'branch' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('params.branch', 'current')->where('canAllBranches', false));

    $this->actingAs($this->world->user('Owner'))
        ->get(route('reports.show', ['report' => 'sales-by-day', 'from' => '2026-11-03', 'to' => '2026-11-03', 'branch' => 'all']))
        ->assertInertia(fn ($page) => $page->where('params.branch', 'all')->where('canAllBranches', true));
});

it('caches the dashboard per tenant and branch for 60 seconds', function (): void {
    $user = $this->world->user('Owner');

    $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard')
            ->where('dashboard.kpis.sales.today', 200_000)
            ->where('dashboard.kpis.invoices.today', 1)
            ->where('dashboard.kpis.low_stock', 1)
            ->where('dashboard.top_customers.0.customer', 'Alice')
            ->has('dashboard.trend', 30));

    $key = DashboardQuery::cacheKey($this->world->tenant->id, $this->world->branch->id);
    expect(Cache::has($key))->toBeTrue()
        ->and(Cache::has(DashboardQuery::cacheKey($this->world->tenant->id, $this->second->id)))->toBeFalse()
        ->and($key)->not->toBe(DashboardQuery::cacheKey($this->world->tenant->id + 1, $this->world->branch->id));
});

it('requires dashboard.view', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->world->tenant->id]);
    $stranger->branches()->attach($this->world->branch);

    $this->actingAs($stranger)->get(route('dashboard'))->assertForbidden();
});
