<?php

declare(strict_types=1);

use App\Actions\Tenancy\SeedTenantRoles;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\PermissionRegistrar;

/** @return array{0: User, 1: Tenant, 2: Branch} */
function searchActor(?string $role = SystemRoles::OWNER): array
{
    $tenant = Tenant::factory()->create();
    $branch = makeBranch($tenant);
    $user = User::factory()->for($tenant)->create();
    $user->branches()->attach($branch);
    app(SeedTenantRoles::class)->handle($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    if ($role !== null) {
        $user->assignRole($role);
    }

    return [$user, $tenant, $branch];
}

function asTenant(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->withoutTenancy($callback);
}

it('requires authentication', function (): void {
    $this->getJson('/app/api/search?q=shirt')->assertUnauthorized();
});

it('finds products by name, sku and barcode and returns only paisa and labels', function (): void {
    [$user, $tenant] = searchActor();
    asTenant($tenant, fn () => Product::factory()->for($tenant)->create(['name' => 'Blue Shirt', 'sku' => 'SH-001', 'barcode' => '8901234']));
    asTenant($tenant, fn () => Product::factory()->for($tenant)->create(['name' => 'Trousers', 'sku' => 'TR-001']));

    $byName = $this->actingAs($user)->getJson('/app/api/search?q=shirt')->assertOk();
    expect($byName->json('products'))->toHaveCount(1)
        ->and($byName->json('products.0.label'))->toBe('Blue Shirt')
        ->and($byName->json('products.0'))->not->toHaveKey('cost_price');

    expect($this->getJson('/app/api/search?q=SH-0')->json('products'))->toHaveCount(1)
        ->and($this->getJson('/app/api/search?q=89012')->json('products'))->toHaveCount(1);
});

it('never returns another tenant\'s rows', function (): void {
    [$user] = searchActor();
    $other = Tenant::factory()->create();
    $otherBranch = makeBranch($other);
    asTenant($other, function () use ($other, $otherBranch): void {
        Product::factory()->for($other)->create(['name' => 'Secret Shirt', 'sku' => 'SEC-1']);
        $customer = Customer::factory()->for($other)->create(['name' => 'Secret Shirt Buyer']);
        Sale::factory()->create([
            'tenant_id' => $other->id, 'branch_id' => $otherBranch->id, 'customer_id' => $customer->id, 'number' => 'S-SECRET-1',
        ]);
    });

    $json = $this->actingAs($user)->getJson('/app/api/search?q=Secret')->assertOk()->json();
    expect($json['products'])->toBe([])->and($json['customers'])->toBe([]);
    expect($this->getJson('/app/api/search?q=S-SECRET')->json('sales'))->toBe([]);
});

it('finds customers by name and by #number, never exposing pin data', function (): void {
    [$user, $tenant] = searchActor();
    asTenant($tenant, fn () => Customer::factory()->for($tenant)->create(['name' => 'Rahim Uddin', 'customer_no' => 10000017, 'pin_hash' => 'x']));

    $r = $this->actingAs($user)->getJson('/app/api/search?q=Rahim')->assertOk();
    expect($r->json('customers.0.label'))->toBe('Rahim Uddin')
        ->and(json_encode($r->json()))->not->toContain('pin');

    expect($this->getJson('/app/api/search?q='.urlencode('#1000'))->json('customers'))->toHaveCount(1);
});

it('finds sales by number inside the selected branch only', function (): void {
    [$user, $tenant, $branch] = searchActor();
    $otherBranch = makeBranch($tenant);
    asTenant($tenant, function () use ($tenant, $branch, $otherBranch): void {
        $customer = Customer::factory()->for($tenant)->create();
        Sale::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'customer_id' => $customer->id, 'number' => 'S-1001']);
        Sale::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $otherBranch->id, 'customer_id' => $customer->id, 'number' => 'S-1002']);
    });

    $r = $this->actingAs($user)->getJson('/app/api/search?q=S-100')->assertOk();
    expect(array_column($r->json('sales'), 'label'))->toBe(['S-1001']);
});

it('omits groups the user is not allowed to view', function (): void {
    [$user, $tenant] = searchActor(null);
    asTenant($tenant, fn () => Product::factory()->for($tenant)->create(['name' => 'Blue Shirt']));

    $this->actingAs($user)->getJson('/app/api/search?q=shirt')->assertOk()
        ->assertExactJson(['products' => [], 'customers' => [], 'sales' => []]);
});

it('returns nothing for a query shorter than two characters', function (): void {
    [$user, $tenant] = searchActor();
    asTenant($tenant, fn () => Product::factory()->for($tenant)->create(['name' => 'Blue Shirt']));

    $this->actingAs($user)->getJson('/app/api/search?q=b')->assertOk()->assertJson(['products' => []]);
});

it('treats LIKE wildcards in the query literally', function (): void {
    [$user, $tenant] = searchActor();
    asTenant($tenant, fn () => Product::factory()->for($tenant)->create(['name' => 'Blue Shirt']));

    expect($this->actingAs($user)->getJson('/app/api/search?q=%25%25')->json('products'))->toBe([]);
});
