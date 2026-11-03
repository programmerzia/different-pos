<?php

declare(strict_types=1);

require_once __DIR__.'/AdminHelpers.php';

use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Permissions\SystemRoles;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

it('needs audit.view', function (): void {
    [$manager] = adminActor(SystemRoles::MANAGER);

    $this->actingAs($manager)->get('/app/settings/audit')->assertForbidden();
});

it('lists this tenant\'s activity, newest first, with the causer and the recorded data', function (): void {
    [$owner, $tenant] = adminActor();
    [$foreignOwner, $foreignTenant] = adminActor();
    Audit::log('thing.first', $tenant, ['note' => 'one'], $owner);
    Audit::log('thing.second', $tenant, ['note' => 'two', 'password' => 'must-not-appear'], $owner);
    Audit::log('thing.foreign', $foreignTenant, ['note' => 'secret'], $foreignOwner);

    $response = $this->actingAs($owner)->get('/app/settings/audit')->assertOk();

    $response->assertInertia(fn (Assert $page) => $page->component('settings/audit')
        ->where('entries.0.event', 'thing.second')
        ->where('entries.0.user', $owner->name)
        ->where('entries.0.properties.note', 'two')
        ->missing('entries.0.properties.password'));
    $events = collect($response->original->getData()['page']['props']['entries'])->pluck('event');
    expect($events)->not->toContain('thing.foreign');
    expect(json_encode($response->original->getData()))->not->toContain('must-not-appear')->not->toContain('secret');
});

it('filters by user, subject type, date range and text', function (): void {
    [$owner, $tenant] = adminActor();
    $other = User::factory()->for($tenant)->create();
    Audit::log('a.one', $tenant, [], $owner);
    Audit::log('b.two', $owner, [], $other);
    Activity::query()->where('event', 'a.one')->update(['created_at' => '2026-01-10 10:00:00']);

    $this->actingAs($owner);
    $this->get('/app/settings/audit?filter[user]='.$other->id)->assertInertia(fn (Assert $p) => $p->where('meta.total', 1)->where('entries.0.event', 'b.two'));
    $this->get('/app/settings/audit?filter[subject]='.urlencode(User::class))->assertInertia(fn (Assert $p) => $p->where('entries.0.subject_type', 'User'));
    $this->get('/app/settings/audit?filter[from]=2026-01-01&filter[to]=2026-01-31')->assertInertia(fn (Assert $p) => $p->where('meta.total', 1)->where('entries.0.event', 'a.one'));
    $this->get('/app/settings/audit?q=b.two')->assertInertia(fn (Assert $p) => $p->where('meta.total', 1));
    $this->get('/app/settings/audit?filter[from]=not-a-date')->assertOk();
});

it('offers only this tenant\'s users and subject types as filters', function (): void {
    [$owner, $tenant] = adminActor();
    [$foreignOwner, $foreignTenant] = adminActor();
    Audit::log('x', $tenant, [], $owner);
    Audit::log('y', $foreignOwner, [], $foreignOwner);

    $this->actingAs($owner)->get('/app/settings/audit')->assertInertia(fn (Assert $p) => $p
        ->has('users', 1)->where('users.0.id', $owner->id)
        ->where('subjects', fn ($subjects) => collect($subjects)->pluck('label')->contains('Tenant')));
    expect($foreignTenant->id)->not->toBe($tenant->id);
});

it('offers the event filter, stamps the request IP and keeps events tenant-scoped', function (): void {
    [$owner, $tenant] = adminActor();
    [$foreignOwner, $foreignTenant] = adminActor();

    $request = Request::create('/app/x', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9']);
    $request->setRouteResolver(fn (): Route => new Route('GET', '/app/x', []));
    app()->instance('request', $request);
    Audit::log('sale.voided', $tenant, [], $owner);
    app()->instance('request', Request::create('/'));
    Audit::log('price.changed', $tenant, [], $owner);
    Audit::log('foreign.only', $foreignTenant, [], $foreignOwner);

    $this->actingAs($owner)->get('/app/settings/audit?filter[event]=sale.voided')->assertInertia(fn (Assert $page) => $page
        ->has('entries', 1)
        ->where('entries.0.event', 'sale.voided')
        ->where('entries.0.ip', '203.0.113.9')
        ->where('events', fn ($events) => collect($events)->contains('sale.voided') && ! collect($events)->contains('foreign.only')));
    $this->actingAs($owner)->get('/app/settings/audit?filter[event]=price.changed')->assertInertia(fn (Assert $page) => $page
        ->where('entries.0.ip', null));
});
