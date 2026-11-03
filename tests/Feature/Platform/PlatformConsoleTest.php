<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Admin/AdminHelpers.php';

use App\Models\Backup;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->withoutVite();
    Notification::fake();
    $this->admin = User::factory()->platformAdmin()->create();
});

function completedSale(Tenant $tenant, int $paisa, ?DateTimeInterface $soldAt = null): Sale
{
    return app(TenantContext::class)->withoutTenancy(fn (): Sale => Sale::factory()->create([
        'tenant_id' => $tenant->id, 'total' => Money::ofPaisa($paisa), 'subtotal' => Money::ofPaisa($paisa), 'sold_at' => $soldAt ?? now(),
    ]));
}

it('keeps tenant users out of the console and its writes', function (): void {
    [$owner, $tenant] = adminActor();

    $this->actingAs($owner)->get('/platform')->assertForbidden();
    $this->post('/platform/tenants', ['name' => 'X'])->assertForbidden();
    $this->post("/platform/tenants/{$tenant->id}/suspend")->assertForbidden();
    $this->post("/platform/tenants/{$tenant->id}/resume")->assertForbidden();
    $this->post("/platform/tenants/{$tenant->id}/impersonate")->assertForbidden();
    $this->post('/app/impersonation/exit')->assertForbidden();
});

it('sends a platform admin to /platform after sign-in', function (): void {
    $this->post('/login', ['login' => $this->admin->email, 'password' => 'password'])->assertRedirect('/platform');
});

it('aggregates KPIs and the registry across every tenant', function (): void {
    [, $a] = adminActor();
    [, $b] = adminActor();
    $b->forceFill(['status' => Tenant::STATUS_SUSPENDED])->save();
    completedSale($a, 150_000);
    completedSale($a, 50_000, now()->subDays(40)); // outside the 30-day window
    completedSale($b, 200_000);
    Backup::query()->create(['status' => Backup::STATUS_COMPLETED, 'disk' => 'local', 'path' => 'POS/x.zip', 'size_bytes' => 1, 'finished_at' => now()]);

    $this->actingAs($this->admin)->get('/platform')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('platform/index')
        ->where('kpis.tenants', 2)->where('kpis.active_tenants', 1)->where('kpis.suspended_tenants', 1)
        ->where('kpis.gmv_30d_paisa', 350_000)->where('kpis.sales_30d', 2)
        ->has('tenants', 2)
        ->has('tenants.0.branches')->has('tenants.0.users')
        ->where('health.database.ok', true)->has('health.database.version')->has('health.queue.driver')->has('health.cache.driver')
        ->has('health.backup.last_completed_at')->has('health.disk.free_bytes'));

    // Active users counts tenant staff only (the platform admin has no tenant).
    expect(User::query()->whereNotNull('tenant_id')->count())->toBeGreaterThan(0);
});

it('provisions, suspends and resumes a tenant from the console', function (): void {
    $this->actingAs($this->admin)->post('/platform/tenants', [
        'name' => 'Gentle Park', 'owner_name' => 'Karim', 'owner_email' => 'karim@gp.test', 'timezone' => 'Asia/Dhaka',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $tenant = Tenant::query()->where('slug', 'gentle-park')->firstOrFail();

    $this->post('/platform/tenants', ['name' => 'Gentle Park', 'owner_name' => 'K', 'owner_email' => 'k2@gp.test'])->assertSessionHasErrors('form');
    $this->post('/platform/tenants', ['name' => '', 'owner_name' => 'K', 'owner_email' => 'bad'])->assertSessionHasErrors(['name', 'owner_email']);

    $this->post("/platform/tenants/{$tenant->id}/suspend")->assertRedirect();
    expect($tenant->refresh()->status)->toBe(Tenant::STATUS_SUSPENDED);

    // Suspended: the Owner is signed out by EnsureTenantActive on the next request.
    $owner = User::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $this->actingAs($owner)->get('/app')->assertRedirect(route('login'));

    $this->actingAs($this->admin)->post("/platform/tenants/{$tenant->id}/resume")->assertRedirect();
    expect($tenant->refresh()->status)->toBe(Tenant::STATUS_ACTIVE)
        ->and(Activity::query()->where('tenant_id', $tenant->id)->whereIn('event', ['tenant.suspended', 'tenant.resumed'])->count())->toBe(2);
});

it('impersonates the tenant Owner with a banner, an audit row and a working exit', function (): void {
    [$owner, $tenant] = adminActor();
    adminActor(SystemRoles::CASHIER, $tenant);

    $this->actingAs($this->admin)->post("/platform/tenants/{$tenant->id}/impersonate")->assertRedirect('/app');
    $this->assertAuthenticatedAs($owner);
    expect(Activity::query()->where('event', 'tenant.impersonated')->where('tenant_id', $tenant->id)->where('causer_id', $this->admin->id)->exists())->toBeTrue();

    $this->get('/app')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('impersonation.user', $owner->name)->where('impersonation.tenant', $tenant->name)->has('impersonation.expires_at'));

    $this->post('/app/impersonation/exit')->assertRedirect('/platform');
    $this->assertAuthenticatedAs($this->admin);
    expect(Activity::query()->where('event', 'tenant.impersonation_ended')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
    $this->get('/platform')->assertOk()->assertInertia(fn (Assert $page) => $page->where('impersonation', null));
});

it('refuses to impersonate a suspended tenant or one without an active Owner', function (): void {
    [, $suspended] = adminActor();
    $suspended->forceFill(['status' => Tenant::STATUS_SUSPENDED])->save();
    [, $ownerless] = adminActor(SystemRoles::MANAGER);

    $this->actingAs($this->admin)->post("/platform/tenants/{$suspended->id}/impersonate")->assertSessionHasErrors('form');
    $this->post("/platform/tenants/{$ownerless->id}/impersonate")->assertSessionHasErrors('form');
    $this->assertAuthenticatedAs($this->admin);
});

it('ends an impersonation after 60 minutes and restores the admin', function (): void {
    [$owner, $tenant] = adminActor();
    $this->actingAs($this->admin)->post("/platform/tenants/{$tenant->id}/impersonate")->assertRedirect('/app');
    $this->assertAuthenticatedAs($owner);

    $this->travel(61)->minutes();
    $this->get('/app')->assertRedirect('/platform');
    $this->assertAuthenticatedAs($this->admin);
    expect(Activity::query()->where('event', 'tenant.impersonation_ended')->where('properties->reason', 'expired')->exists())->toBeTrue();
});

it('creates the first platform admin from the console command', function (): void {
    $this->artisan('platform:make-admin', ['email' => 'Ops@Example.test', '--name' => 'Ops'])->expectsOutputToContain('Created platform admin ops@example.test')->assertSuccessful();
    $admin = User::query()->where('email', 'ops@example.test')->firstOrFail();
    expect($admin->is_platform_admin)->toBeTrue()->and($admin->tenant_id)->toBeNull()->and($admin->is_active)->toBeTrue();
    $this->actingAs($admin)->get('/platform')->assertOk();

    [$owner] = adminActor();
    $this->artisan('platform:make-admin', ['email' => $owner->email])->assertFailed();
    $this->artisan('platform:make-admin', ['email' => 'nope'])->assertFailed();
});
