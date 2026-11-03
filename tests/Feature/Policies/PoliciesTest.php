<?php

declare(strict_types=1);

use App\Actions\Tenancy\SeedTenantRoles;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\MembershipCard;
use App\Models\Message;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Purchase;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

/**
 * The expected mapping typed out independently of the policies: model => ability => permission(s) any of
 * (empty list = any member of the tenant). Class-level abilities take the class, the rest an instance.
 */
function policyMatrix(): array
{
    $catalog = ['viewAny' => 'products.view', 'view' => 'products.view', 'create' => 'products.create', 'update' => 'products.update', 'delete' => 'products.delete'];
    $customerCrud = ['viewAny' => 'customers.view', 'view' => 'customers.view', 'create' => 'customers.create', 'update' => 'customers.update', 'delete' => 'customers.delete'];
    $settingsCrud = ['create' => 'settings.manage', 'update' => 'settings.manage', 'delete' => 'settings.manage'];

    return [
        Category::class => $catalog, Brand::class => $catalog, Unit::class => $catalog, ProductFamily::class => $catalog,
        TaxRate::class => ['viewAny' => ['products.view', 'settings.manage'], 'view' => ['products.view', 'settings.manage']] + $settingsCrud,
        Product::class => $catalog + ['viewCost' => 'products.view_cost'],
        CustomerGroup::class => $customerCrud,
        Customer::class => $customerCrud + ['resetPin' => 'customers.reset_pin'],
        MembershipCard::class => ['viewAny' => 'cards.view', 'view' => 'cards.view', 'create' => 'cards.issue', 'reissue' => 'cards.issue', 'block' => 'cards.issue'],
        Vendor::class => ['viewAny' => 'vendors.view', 'view' => 'vendors.view', 'create' => 'vendors.create', 'update' => 'vendors.update', 'delete' => 'vendors.delete'],
        PaymentMethod::class => ['viewAny' => ['pos.sell', 'payments.collect', 'payments.pay', 'settings.manage'], 'view' => ['pos.sell', 'payments.collect', 'payments.pay', 'settings.manage']] + $settingsCrud,
        Register::class => ['viewAny' => ['pos.sell', 'settings.manage'], 'view' => ['pos.sell', 'settings.manage']] + $settingsCrud,
        Purchase::class => ['viewAny' => 'purchases.view', 'view' => 'purchases.view', 'create' => 'purchases.create', 'update' => 'purchases.create', 'receive' => 'purchases.receive', 'cancel' => 'purchases.cancel'],
        StockAdjustment::class => ['viewAny' => 'stock.view', 'view' => 'stock.view', 'create' => 'stock.adjust'],
        Sale::class => ['viewAny' => 'sales.view', 'view' => 'sales.view', 'create' => 'pos.sell', 'void' => 'sales.void'],
        SaleReturn::class => ['viewAny' => 'returns.view', 'view' => 'returns.view', 'create' => 'returns.create'],
        Payment::class => ['viewAny' => ['payments.collect', 'payments.pay'], 'view' => ['payments.collect', 'payments.pay'], 'collect' => 'payments.collect', 'pay' => 'payments.pay'],
        Message::class => ['viewAny' => 'sms.view', 'view' => 'sms.view', 'configure' => 'settings.manage', 'send' => 'sms.send'],
        User::class => ['viewAny' => 'users.manage', 'view' => 'users.manage', 'create' => 'users.manage', 'update' => 'users.manage', 'delete' => 'users.manage'],
        Branch::class => ['viewAny' => [], 'view' => []] + $settingsCrud,
        Tenant::class => ['view' => [], 'update' => 'settings.manage'],
    ];
}

const CLASS_LEVEL = ['viewAny', 'create', 'collect', 'pay', 'configure', 'send'];

/** @return array{0: Tenant, 1: array<string, User>} */
function tenantWithRoleUsers(): array
{
    $tenant = Tenant::factory()->create();
    app(SeedTenantRoles::class)->handle($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $users = [];
    foreach (array_keys(SystemRoles::definitions()) as $role) {
        $users[$role] = User::factory()->for($tenant)->create();
        $users[$role]->assignRole($role);
    }

    return [$tenant, $users];
}

function subjectFor(string $model, Tenant $tenant): Tenant|Model
{
    if ($model === Tenant::class) {
        return $tenant;
    }
    $instance = new $model;
    $instance->setAttribute('tenant_id', $tenant->id);

    return $instance;
}

it('answers every policy ability exactly as the role permissions imply', function (): void {
    [$tenant, $users] = tenantWithRoleUsers();
    app(TenantContext::class)->set($tenant);
    $permissionsByRole = array_map(static fn (array $d): array => $d['permissions'], SystemRoles::definitions());
    $checked = 0;

    foreach (policyMatrix() as $model => $abilities) {
        foreach ($abilities as $ability => $needed) {
            $needed = (array) $needed;
            $argument = in_array($ability, CLASS_LEVEL, true) ? $model : subjectFor($model, $tenant);
            foreach ($users as $role => $user) {
                $expected = $needed === [] || array_intersect($needed, $permissionsByRole[$role]) !== [];
                expect(Gate::forUser($user)->allows($ability, $argument))
                    ->toBe($expected, "{$role} {$model}::{$ability}");
                $checked++;
            }
        }
    }

    expect($checked)->toBeGreaterThan(300);
});

it('pins the headline role behaviours', function (): void {
    [$tenant, $users] = tenantWithRoleUsers();
    app(TenantContext::class)->set($tenant);
    $sale = subjectFor(Sale::class, $tenant);

    expect(Gate::forUser($users['Owner'])->allows('void', $sale))->toBeTrue()
        ->and(Gate::forUser($users['Cashier'])->allows('create', Sale::class))->toBeTrue()
        ->and(Gate::forUser($users['Cashier'])->allows('void', $sale))->toBeFalse()
        ->and(Gate::forUser($users['Stock Keeper'])->allows('viewAny', Customer::class))->toBeFalse()
        ->and(Gate::forUser($users['Accountant'])->allows('create', Sale::class))->toBeFalse()
        ->and(Gate::forUser($users['Manager'])->allows('update', $tenant))->toBeFalse();
});

it('denies even the owner a record of another tenant, and an owner of another tenant', function (): void {
    [$tenant, $users] = tenantWithRoleUsers();
    $other = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $foreign = subjectFor(Product::class, $other);
    expect(Gate::forUser($users['Owner'])->allows('view', $foreign))->toBeFalse()
        ->and(Gate::forUser($users['Owner'])->allows('update', $other))->toBeFalse()
        ->and(Gate::forUser($users['Owner'])->allows('view', subjectFor(Product::class, $tenant)))->toBeTrue();

    // Owner of tenant B acting while the context is tenant A.
    app(SeedTenantRoles::class)->handle($other);
    app(PermissionRegistrar::class)->setPermissionsTeamId($other->id);
    $foreignOwner = app(TenantContext::class)->withoutTenancy(fn (): User => User::factory()->for($other)->create());
    $foreignOwner->assignRole('Owner');
    expect(Gate::forUser($foreignOwner)->allows('view', subjectFor(Product::class, $tenant)))->toBeFalse()
        ->and(Gate::forUser($foreignOwner)->allows('viewAny', Product::class))->toBeFalse();
});

it('fails closed without a tenant context', function (): void {
    [, $users] = tenantWithRoleUsers();
    app(TenantContext::class)->forget();

    expect(Gate::forUser($users['Owner'])->allows('viewAny', Product::class))->toBeFalse();
});
