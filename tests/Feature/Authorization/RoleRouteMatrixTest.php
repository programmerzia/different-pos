<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\ProductFamily;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Vendor;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\CatalogWorld;
use Tests\Fixtures\SalesWorld;

/*
| Every GET page under /app, once per system role. The expected answer comes from the route's own
| `can:` middleware where it has one, otherwise from the table below (route name => what the
| controller's Gate/policy call needs). A GET route missing from the table fails the test, so the
| matrix cannot drift when a page is added.
|
| A requirement is a permission, or a list of permissions of which any one is enough; all
| requirements of a route must hold. An empty list means any signed-in member of the tenant.
*/

/** @return array<string, list<string|list<string>>> */
function routePermissions(): array
{
    $customers = ['customers.view'];
    $products = ['products.view'];

    return [
        // Open to every member (palette lookup, branch picker, forced password change).
        'api.search' => [], 'branch.select' => [], 'password.change' => [],

        'dashboard' => ['dashboard.view'],
        'reports.index' => ['reports.view'], 'reports.show' => ['reports.view'],

        'products.index' => $products, 'products.images' => $products, 'products.create' => ['products.create'], 'products.edit' => ['products.update'],
        'brands.index' => $products, 'categories.index' => $products, 'families.show' => $products,

        'vendors.index' => ['vendors.view'], 'vendors.create' => ['vendors.create'],
        'vendors.show' => ['vendors.view'], 'vendors.edit' => ['vendors.update'],
        'purchases.index' => ['purchases.view'], 'purchases.create' => ['purchases.create'],
        'purchases.lookup' => ['purchases.create'], 'purchases.show' => ['purchases.view'],

        'stock.index' => ['stock.view'], 'stock.movements' => ['stock.view', 'products.view'],
        'adjustments.index' => ['stock.view'], 'adjustments.create' => ['stock.adjust'], 'adjustments.lookup' => ['stock.adjust'],

        'customers.index' => $customers, 'customers.export' => $customers, 'customers.create' => ['customers.create'], 'customers.show' => $customers,
        'customers.edit' => ['customers.update'], 'customers.search' => $customers, 'customers.check-phone' => $customers,
        'customers.statement.index' => $customers, 'customers.statement' => $customers,
        'dues.index' => $customers, 'dues.show' => $customers,
        'cards.index' => ['cards.view'], 'cards.print' => ['cards.issue'], 'cards.export' => ['cards.issue'],

        'sales.index' => ['sales.view'], 'sales.show' => ['sales.view'], 'sales.receipt' => ['sales.view'],
        'returns.index' => ['returns.view'], 'returns.show' => ['returns.view'], 'returns.receipt' => ['returns.view'],
        'returns.create' => ['returns.create'], 'exchanges.create' => ['returns.create', 'pos.sell'],
        'payments.receipt' => [['payments.collect', 'payments.pay']],

        'messages.index' => $customers,
        'settings.sms.edit' => ['settings.manage'],
        'settings.audit' => ['audit.view'], 'settings.business' => ['settings.manage'], 'branches.index' => ['settings.manage'],
        'registers.index' => ['settings.manage'], 'payment-methods.index' => ['settings.manage'], 'taxes.index' => ['settings.manage'],
        'units.index' => ['settings.manage'], 'users.index' => ['users.manage'], 'users.create' => ['users.manage'],
        'users.edit' => ['users.manage'], 'roles.index' => ['users.manage'],

        'pos.register' => ['pos.sell'], 'pos.customers' => ['pos.sell'], 'pos.customers.show' => ['pos.sell'],
        'pos.products' => ['pos.sell'], 'pos.today' => ['pos.sell'],
    ];
}

/** @return list<LaravelRoute> */
function appGetRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        static fn (LaravelRoute $route): bool => in_array('GET', $route->methods(), true)
            && ($route->uri() === 'app' || str_starts_with($route->uri(), 'app/')),
    ));
}

/** Permissions the route's own `can:` middleware asks for. @return list<string> */
function middlewarePermissions(LaravelRoute $route): array
{
    $found = [];
    foreach ($route->gatherMiddleware() as $middleware) {
        if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
            array_push($found, ...explode(',', substr($middleware, 4)));
        }
    }

    return $found;
}

/** @param list<string|list<string>> $requirements */
function roleSatisfies(string $role, array $requirements): bool
{
    $granted = SystemRoles::definitions()[$role]['permissions'];

    foreach ($requirements as $requirement) {
        $anyOf = is_array($requirement) ? $requirement : [$requirement];
        if (array_intersect($anyOf, $granted) === []) {
            return false;
        }
    }

    return true;
}

/**
 * @param  array<string, string|int>  $values
 * @return array<string, string|int>
 */
function routeParameters(LaravelRoute $route, array $values): array
{
    $parameters = [];
    foreach ($route->parameterNames() as $name) {
        $parameters[$name] = $values[$name] ?? throw new RuntimeException(
            "Route [{$route->getName()}] takes {{$name}} and the matrix has no fixture for it: add one in beforeEach.",
        );
    }

    return $parameters;
}

beforeEach(function (): void {
    $world = SalesWorld::make();
    $context = app(TenantContext::class);
    foreach ($world->users as $user) {
        $user->branches()->attach($world->branch);
    }

    $tenantId = $world->tenant->id;
    $branchId = $world->branch->id;
    $owner = $world->user(SystemRoles::OWNER);

    $product = $world->product();
    $family = ProductFamily::factory()->create(['tenant_id' => $tenantId]);
    $vendor = Vendor::factory()->create(['tenant_id' => $tenantId]);
    $customer = $world->customer();
    $sale = Sale::factory()->create(['tenant_id' => $tenantId, 'branch_id' => $branchId, 'number' => 'DF1-000001', 'customer_id' => $customer->id]);

    $this->world = $world;
    $this->fixtures = [
        'product' => $product->uuid,
        'family' => $family->uuid,
        'vendor' => $vendor->uuid,
        'customer' => $customer->uuid,
        'purchase' => Purchase::factory()->create(['tenant_id' => $tenantId, 'branch_id' => $branchId, 'vendor_id' => $vendor->id])->uuid,
        'sale' => $sale->id,
        'return' => SaleReturn::factory()->create(['tenant_id' => $tenantId, 'branch_id' => $branchId, 'sale_id' => $sale->id, 'customer_id' => $customer->id])->id,
        'payment' => Payment::factory()->create(['tenant_id' => $tenantId, 'branch_id' => $branchId, 'party_id' => $customer->id, 'received_by' => $owner->id])->id,
        'user' => $owner->id,
        'report' => 'sales-by-product',
    ];

    // Tenant B: a lone owner who must never reach any of the records above.
    $other = $context->withoutTenancy(static fn (): CatalogWorld => CatalogWorld::make());
    $this->outsider = $other->user;
    $context->set($world->tenant, $world->branch);
});

it('knows a permission for every GET route under /app', function (): void {
    $table = routePermissions();
    $names = [];
    $unknown = [];

    foreach (appGetRoutes() as $route) {
        $name = $route->getName() ?? '';
        $names[] = $name;
        if (! array_key_exists($name, $table)) {
            $unknown[] = $name !== '' ? $name : $route->uri();
        }
    }

    expect($unknown)->toBe([], 'GET routes with no entry in routePermissions(): '.implode(', ', $unknown))
        ->and(array_values(array_diff(array_keys($table), $names)))->toBe([], 'routePermissions() lists routes that no longer exist');
});

it('keeps the table in step with every can: middleware', function (): void {
    $table = routePermissions();

    foreach (appGetRoutes() as $route) {
        $fromMiddleware = middlewarePermissions($route);
        if ($fromMiddleware !== []) {
            expect($table[$route->getName()] ?? null)->toBe($fromMiddleware, "[{$route->getName()}] table disagrees with its can: middleware");
        }
    }
});

it('gives each role exactly the pages its permissions allow', function (): void {
    $table = routePermissions();
    $problems = [];

    foreach (appGetRoutes() as $route) {
        $name = (string) $route->getName();
        $requirements = $table[$name] ?? null;
        if ($requirements === null) {
            continue; // reported by the first test
        }
        $url = route($name, routeParameters($route, $this->fixtures));

        foreach (SystemRoles::definitions() as $role => $_) {
            $status = $this->actingAs($this->world->user($role))->get($url)->getStatusCode();
            $allowed = roleSatisfies($role, $requirements);

            $ok = $allowed ? ($status === 200 || ($status >= 300 && $status < 400)) : $status === 403;
            if (! $ok) {
                $problems[] = sprintf('%s as %s: got %d, expected %s', $name, $role, $status, $allowed ? '200/3xx' : '403');
            }
        }
    }

    expect($problems)->toBe([], implode("\n", $problems));
});

it('answers 404 when another tenant asks for a record by id', function (): void {
    $table = routePermissions();
    $problems = [];
    $checked = 0;

    foreach (appGetRoutes() as $route) {
        $name = (string) $route->getName();
        // Reports are named, not stored; the tenant filter applies to their rows, not to the URL.
        $recordParameters = array_values(array_diff($route->parameterNames(), ['report']));
        if ($recordParameters === [] || ! array_key_exists($name, $table)) {
            continue;
        }

        $status = $this->actingAs($this->outsider)->get(route($name, routeParameters($route, $this->fixtures)))->getStatusCode();
        $checked++;
        if ($status !== 404) {
            $problems[] = sprintf('%s: got %d, expected 404', $name, $status);
        }
    }

    expect($checked)->toBeGreaterThan(10)
        ->and($problems)->toBe([], implode("\n", $problems));
});
