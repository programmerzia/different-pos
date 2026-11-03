<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTenantActive;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    // Same stack as routes/api-internal.php, for endpoints that do not exist yet.
    Route::middleware(['web', 'auth', 'tenant.active', 'tenant.resolve', 'branch.selected', 'locale'])
        ->get('/app/api/_probe', fn () => response()->json(['ok' => true]));
});

function tenantUser(?Tenant $tenant = null, int $branches = 1, array $attributes = [], bool $canSwitch = false): array
{
    $tenant ??= Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create($attributes);
    if ($canSwitch) {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->givePermissionTo(Permission::findOrCreate('branches.switch', 'web'));
    }
    $made = [];
    for ($i = 0; $i < $branches; $i++) {
        $branch = makeBranch($tenant);
        $user->branches()->attach($branch);
        $made[] = $branch;
    }

    return [$user, $tenant, $made];
}

/* ---- guest ---- */

it('redirects guests away from /app and 401s the internal api', function (): void {
    $this->get('/app')->assertRedirect(route('login'));
    $this->getJson('/app/api/_probe')->assertUnauthorized();
});

/* ---- EnsureTenantActive ---- */

it('logs out a user of a suspended tenant with a flash key', function (): void {
    [$user, $tenant] = tenantUser();
    $tenant->forceFill(['status' => Tenant::STATUS_SUSPENDED])->save();

    $this->actingAs($user)->get('/app')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status_key', EnsureTenantActive::SUSPENDED_MESSAGE_KEY);
    $this->assertGuest();
});

it('answers 403 json for a suspended tenant on json requests', function (): void {
    [$user, $tenant] = tenantUser();
    $tenant->forceFill(['status' => Tenant::STATUS_SUSPENDED])->save();

    $this->actingAs($user)->getJson('/app')->assertForbidden()->assertJson(['code' => 'tenant_suspended']);
});

/* ---- ResolveTenant ---- */

it('sets the tenant context and permission team for an active tenant', function (): void {
    $this->withoutVite();
    [$user, $tenant] = tenantUser(canSwitch: true);

    $this->actingAs($user)->get(route('branch.select'))->assertOk();

    expect(app(TenantContext::class)->tenantId())->toBe($tenant->id)
        ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($tenant->id);
});

it('blocks platform admins from tenant routes and allows them on /platform', function (): void {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)->get('/app')->assertForbidden();
    $this->actingAs($admin)->get(route('branch.select'))->assertForbidden();
    $this->actingAs($admin)->get('/platform')->assertOk()->assertJson(['ok' => true]);
});

it('blocks tenant users from /platform', function (): void {
    [$user] = tenantUser();

    $this->actingAs($user)->get('/platform')->assertForbidden();
});

/* ---- EnsureBranchSelected ---- */

it('auto-selects the only branch and sets the branch context', function (): void {
    [$user, , [$branch]] = tenantUser();

    $this->actingAs($user)->get('/app'); // 403 (no permission) but the stack already ran

    expect(session('branch_id'))->toBe($branch->id)
        ->and(app(TenantContext::class)->branchId())->toBe($branch->id);
});

it('redirects to branch.select when several branches and none chosen', function (): void {
    [$user] = tenantUser(branches: 2, canSwitch: true);

    $this->actingAs($user)->get('/app')->assertRedirect(route('branch.select'));
});

it('accepts a session branch that is assigned and re-checks it every request', function (): void {
    [$user, , [, $second]] = tenantUser(branches: 3, canSwitch: true);

    $this->actingAs($user)->withSession(['branch_id' => $second->id])->get('/app');
    expect(app(TenantContext::class)->branchId())->toBe($second->id);

    $user->branches()->detach($second);
    $this->actingAs($user)->withSession(['branch_id' => $second->id])->get('/app')
        ->assertRedirect(route('branch.select'));
});

it('redirects a user without any branch to branch.select', function (): void {
    $this->withoutVite();
    [$user] = tenantUser(branches: 0);

    $this->actingAs($user)->get('/app')->assertRedirect(route('branch.select'));
    $this->actingAs($user)->get(route('branch.select'))->assertOk();
});

it('ignores inactive branches', function (): void {
    [$user, , [$branch]] = tenantUser();
    app(TenantContext::class)->withoutTenancy(fn () => $branch->forceFill(['is_active' => false])->save());

    $this->actingAs($user)->withSession(['branch_id' => $branch->id])->get('/app')
        ->assertRedirect(route('branch.select'));
});

it('rejects a session branch of another tenant', function (): void {
    [$user] = tenantUser(branches: 2, canSwitch: true);
    $foreign = app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->create());

    $this->actingAs($user)->withSession(['branch_id' => $foreign->id])->get('/app')
        ->assertRedirect(route('branch.select'));
    expect(session('branch_id'))->toBeNull();
});

it('rejects a foreign branch even when it is attached to the user', function (): void {
    [$user] = tenantUser(branches: 0);
    $foreign = app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->create());
    $user->branches()->attach($foreign);

    $this->actingAs($user)->get('/app')->assertRedirect(route('branch.select'));
});

it('answers 409 json when a branch is required', function (): void {
    [$user] = tenantUser(branches: 2, canSwitch: true);

    $this->actingAs($user)->getJson('/app/api/_probe')->assertStatus(409)->assertJson(['code' => 'branch_required']);
});

/* ---- SetLocale ---- */

it('uses the user locale preference', function (): void {
    [$user] = tenantUser(attributes: ['preferences' => ['locale' => 'bn']]);

    $this->actingAs($user)->get('/app');
    expect(app()->getLocale())->toBe('bn');
});

it('falls back to the tenant locale', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['locale' => 'bn'])->save();
    [$user] = tenantUser($tenant);

    $this->actingAs($user)->get('/app');
    expect(app()->getLocale())->toBe('bn');
});

it('ignores unsupported locales', function (): void {
    [$user] = tenantUser(attributes: ['preferences' => ['locale' => 'fr']]);

    $this->actingAs($user)->get('/app');
    expect(app()->getLocale())->toBe('en');
});

/* ---- device api ---- */

it('serves the unauthenticated ping probe without a session', function (): void {
    $this->get('/api/pos/v1/ping')->assertNoContent();
    expect(route('pos-api.ping', absolute: false))->toBe('/api/pos/v1/ping');
});
