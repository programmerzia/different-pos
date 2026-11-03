<?php

declare(strict_types=1);

require_once __DIR__.'/AdminHelpers.php';

use App\Models\Activity;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditPresenter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/** Rows for the activity log: subject names instead of "Role #9", and tenant-local timestamps. */
$present = static fn (int $tenantId): array => (new AuditPresenter($tenantId, 'Asia/Dhaka'))
    ->rows(Activity::query()->where('tenant_id', $tenantId)->with('causer')->orderBy('id')->get()->all());

it('names users, roles, branches, customers, products and sales', function () use ($present): void {
    [$owner, $tenant] = adminActor();
    $context = app(TenantContext::class);
    $context->set($tenant);
    $role = Role::query()->where('tenant_id', $tenant->id)->where('name', 'Manager')->firstOrFail();
    $branch = Branch::factory()->for($tenant)->create(['name' => 'Gulshan']);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Roman']);
    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Polo', 'variant_label' => 'M / Red']);
    Activity::query()->delete();

    foreach ([$owner, $role, $branch, $customer, $product] as $subject) {
        Audit::log('thing.happened', $subject, [], $owner);
    }

    expect(array_column($present($tenant->id), 'subject_label'))->toBe([
        'User · '.$owner->name,
        'Role · Manager',
        'Branch · Gulshan',
        'Customer · Roman',
        'Product · Polo (M / Red)',
    ]);
});

it('falls back to type and id for a deleted subject or a type without a name', function () use ($present): void {
    [$owner, $tenant] = adminActor();
    app(TenantContext::class)->set($tenant);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Gone']);
    Activity::query()->delete();
    Audit::log('customer.deleted', $customer, [], $owner);
    $id = $customer->id;
    Customer::query()->whereKey($id)->forceDelete();

    expect($present($tenant->id)[0]['subject_label'])->toBe('Customer #'.$id);
});

it('never resolves a name from another tenant', function () use ($present): void {
    [$owner, $tenant] = adminActor();
    [$foreignOwner] = adminActor();
    app(TenantContext::class)->set($tenant);
    Activity::query()->delete();
    Audit::log('user.updated', $foreignOwner, [], $owner);
    Activity::query()->update(['tenant_id' => $tenant->id]); // a forged cross-tenant subject

    expect($present($tenant->id)[0]['subject_label'])->toBe('User #'.$foreignOwner->id);
});

it('sends the time in the tenant timezone with its offset', function () use ($present): void {
    [$owner, $tenant] = adminActor();
    app(TenantContext::class)->set($tenant);
    Activity::query()->delete();
    Audit::log('x.y', $tenant, [], $owner);
    Activity::query()->update(['created_at' => Carbon::parse('2026-11-03 12:26:27', 'UTC')]);

    expect($present($tenant->id)[0]['created_at'])->toBe('2026-11-03T18:26:27+06:00');
});

it('serves the subject label on the audit page', function (): void {
    [$owner, $tenant] = adminActor();
    Audit::log('settings.business_updated', $tenant, [], $owner);

    $this->actingAs($owner)->get('/app/settings/audit?filter[event]=settings.business_updated')
        ->assertInertia(fn (Assert $page) => $page->where('entries.0.subject_label', 'Tenant · '.$tenant->name));
});
