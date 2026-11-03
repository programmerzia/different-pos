<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Tenancy\TenantContext;

function auditTenant(): Tenant
{
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    return $tenant;
}

it('stamps tenant_id on model activity', function (): void {
    $tenant = auditTenant();

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $product->update(['name' => 'Renamed']);

    $rows = Activity::query()->where('subject_type', $product->getMorphClass())->where('subject_id', $product->id)->get();
    expect($rows->pluck('event')->all())->toBe(['created', 'updated'])
        ->and($rows->every(fn (Activity $a): bool => $a->tenant_id === $tenant->id))->toBeTrue()
        ->and($rows->last()->properties->all())->toBeArray();
});

it('does not log an update that changed nothing', function (): void {
    $tenant = auditTenant();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $before = Activity::query()->count();

    $customer->update(['name' => $customer->name]);

    expect(Activity::query()->count())->toBe($before);
});

it('never writes secrets into the audit trail', function (): void {
    $tenant = auditTenant();
    $user = User::factory()->for($tenant)->create();
    $user->update([
        'password' => 'a-new-secret-password', 'name' => 'Changed Name',
    ]);
    $user->forceFill(['pin_hash' => 'x', 'legacy_password_md5' => 'y', 'two_factor_secret' => 'z'])->save();
    Audit::log('pin.reset', $user, ['pin_hash' => 'leak', 'nested' => ['member_pin_box' => 'leak', 'ok' => 1], 'ok' => true]);

    $json = Activity::query()->where('subject_id', $user->id)->get()->map(
        fn (Activity $a): string => json_encode([$a->properties, $a->attribute_changes], JSON_THROW_ON_ERROR),
    )->implode(' ');

    expect(Activity::query()->where('subject_id', $user->id)->count())->toBeGreaterThan(1);
    foreach (['pin_hash', 'password', 'two_factor', 'legacy_password_md5', 'member_pin_box', 'leak', 'a-new-secret-password'] as $forbidden) {
        expect($json)->not->toContain($forbidden);
    }
    expect($json)->toContain('Changed Name');
});

it('records a custom event with causer, properties and tenant', function (): void {
    $tenant = auditTenant();
    $actor = User::factory()->for($tenant)->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    Audit::log('product.price_overridden', $product, ['from' => 100, 'to' => 90], $actor);

    $row = Activity::query()->where('event', 'product.price_overridden')->firstOrFail();
    expect($row->tenant_id)->toBe($tenant->id)
        ->and($row->causer_id)->toBe($actor->id)
        ->and($row->subject_id)->toBe($product->id)
        ->and($row->properties->all())->toEqual(['from' => 100, 'to' => 90]);
});

it('allows a null tenant for platform-level activity', function (): void {
    app(TenantContext::class)->forget();
    $admin = User::factory()->platformAdmin()->create();

    Audit::log('platform.login', $admin, [], $admin);

    expect(Activity::query()->where('event', 'platform.login')->firstOrFail()->tenant_id)->toBeNull();
});
