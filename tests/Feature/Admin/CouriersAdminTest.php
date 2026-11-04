<?php

declare(strict_types=1);

require_once __DIR__.'/AdminHelpers.php';

use App\Models\Activity;
use App\Models\Courier;
use App\Support\Permissions\SystemRoles;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

function makeCourier($tenant, array $attributes = []): Courier
{
    return inTenant($tenant, fn (): Courier => Courier::factory()->create(['tenant_id' => $tenant->id] + $attributes));
}

function courierRows($tenant): array
{
    return inTenant($tenant, fn (): array => Courier::query()->where('tenant_id', $tenant->id)->orderBy('name')->get()->all());
}

it('needs settings.manage to view or change couriers', function (): void {
    [$manager, $tenant] = adminActor(SystemRoles::MANAGER);
    $courier = makeCourier($tenant);
    $this->actingAs($manager);

    $this->get('/app/settings/couriers')->assertForbidden();
    $this->post('/app/settings/couriers', ['name' => 'X'])->assertForbidden();
    $this->put("/app/settings/couriers/{$courier->id}", ['name' => 'Y'])->assertForbidden();
    $this->post("/app/settings/couriers/{$courier->id}/default")->assertForbidden();
    $this->delete("/app/settings/couriers/{$courier->id}")->assertForbidden();
    expect(courierRows($tenant))->toHaveCount(1);
});

it('lists, creates and edits couriers', function (): void {
    [$owner, $tenant] = adminActor();
    $this->actingAs($owner);

    $this->post('/app/settings/couriers', ['name' => 'Pathao', 'phone' => '01712345678'])->assertRedirect();
    $courier = courierRows($tenant)[0];
    expect($courier->name)->toBe('Pathao')->and($courier->phone)->toBe('01712345678')
        ->and($courier->is_default)->toBeFalse()->and($courier->is_active)->toBeTrue();

    $this->put("/app/settings/couriers/{$courier->id}", ['name' => 'Pathao Express', 'phone' => ''])->assertRedirect();
    expect($courier->refresh()->name)->toBe('Pathao Express')->and($courier->phone)->toBeNull();

    $this->withoutVite()->get('/app/settings/couriers')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/couriers')->has('couriers', 1)->where('couriers.0.name', 'Pathao Express'));
});

it('refuses a duplicate or empty name within the tenant', function (): void {
    [$owner, $tenant] = adminActor();
    makeCourier($tenant, ['name' => 'Sundarban']);
    $this->actingAs($owner);

    $this->post('/app/settings/couriers', ['name' => 'Sundarban'])->assertSessionHasErrors('name');
    $this->post('/app/settings/couriers', ['name' => 'SUNDARBAN'])->assertSessionHasErrors();
    $this->post('/app/settings/couriers', ['name' => ''])->assertSessionHasErrors('name');
    expect(courierRows($tenant))->toHaveCount(1);
});

it('allows the same name in another tenant', function (): void {
    [$owner, $tenant] = adminActor();
    [, $other] = adminActor();
    makeCourier($other, ['name' => 'RedX']);

    $this->actingAs($owner)->post('/app/settings/couriers', ['name' => 'RedX'])->assertRedirect()->assertSessionHasNoErrors();
    expect(courierRows($tenant))->toHaveCount(1);
});

it('keeps one default per tenant', function (): void {
    [$owner, $tenant] = adminActor();
    [, $other] = adminActor();
    $theirs = makeCourier($other, ['name' => 'Theirs', 'is_default' => true]);
    $this->actingAs($owner);

    $this->post('/app/settings/couriers', ['name' => 'A', 'is_default' => true])->assertRedirect();
    $this->post('/app/settings/couriers', ['name' => 'B', 'is_default' => true])->assertRedirect();
    [$a, $b] = courierRows($tenant);
    expect($a->is_default)->toBeFalse()->and($b->is_default)->toBeTrue();

    $this->post("/app/settings/couriers/{$a->id}/default")->assertRedirect();
    expect($a->refresh()->is_default)->toBeTrue()->and($b->refresh()->is_default)->toBeFalse();

    $this->put("/app/settings/couriers/{$b->id}", ['name' => 'B', 'is_default' => true])->assertRedirect();
    expect($a->refresh()->is_default)->toBeFalse()->and($b->refresh()->is_default)->toBeTrue()
        ->and(inTenant($other, fn () => $theirs->refresh()->is_default))->toBeTrue();
});

it('has a database guard against two defaults', function (): void {
    [, $tenant] = adminActor();
    makeCourier($tenant, ['name' => 'A', 'is_default' => true]);

    expect(fn () => makeCourier($tenant, ['name' => 'B', 'is_default' => true]))->toThrow(QueryException::class);
});

it('will not make an inactive courier the default, and deactivating the default clears it', function (): void {
    [$owner, $tenant] = adminActor();
    $off = makeCourier($tenant, ['name' => 'Off', 'is_active' => false]);
    $on = makeCourier($tenant, ['name' => 'On', 'is_default' => true]);
    $this->actingAs($owner);

    $this->post("/app/settings/couriers/{$off->id}/default")->assertSessionHasErrors('form');
    $this->put("/app/settings/couriers/{$off->id}", ['name' => 'Off', 'is_default' => true, 'is_active' => false])->assertSessionHasErrors('form');
    expect($on->refresh()->is_default)->toBeTrue();

    $this->put("/app/settings/couriers/{$on->id}", ['name' => 'On', 'is_active' => false])->assertRedirect();
    expect($on->refresh()->is_default)->toBeFalse()->and($on->is_active)->toBeFalse();
});

it('deletes a courier, including the default, leaving no default', function (): void {
    [$owner, $tenant] = adminActor();
    $default = makeCourier($tenant, ['name' => 'Def', 'is_default' => true]);
    makeCourier($tenant, ['name' => 'Other']);

    $this->actingAs($owner)->delete("/app/settings/couriers/{$default->id}")->assertRedirect();
    $left = courierRows($tenant);
    expect($left)->toHaveCount(1)->and($left[0]->is_default)->toBeFalse();
});

it('404s another tenant courier on every route and lists none of them', function (): void {
    [$owner] = adminActor();
    [, $other] = adminActor();
    $foreign = makeCourier($other, ['name' => 'Theirs']);
    $this->withoutVite()->actingAs($owner);

    $this->put("/app/settings/couriers/{$foreign->id}", ['name' => 'Mine'])->assertNotFound();
    $this->post("/app/settings/couriers/{$foreign->id}/default")->assertNotFound();
    $this->delete("/app/settings/couriers/{$foreign->id}")->assertNotFound();
    $this->get('/app/settings/couriers')->assertInertia(fn (Assert $page) => $page->has('couriers', 0));
    expect(courierRows($other))->toHaveCount(1);
});

it('audits courier changes', function (): void {
    [$owner, $tenant] = adminActor();
    $this->actingAs($owner)->post('/app/settings/couriers', ['name' => 'Audited'])->assertRedirect();
    $courier = courierRows($tenant)[0];
    $this->post("/app/settings/couriers/{$courier->id}/default")->assertRedirect();
    $this->delete("/app/settings/couriers/{$courier->id}")->assertRedirect();

    $events = Activity::query()->where('subject_type', Courier::class)->pluck('event')->all();
    expect($events)->toContain('courier.created', 'courier.default_set', 'courier.deleted');
});
