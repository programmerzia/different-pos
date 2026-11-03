<?php

declare(strict_types=1);

use App\Support\Tenancy\TenantContext;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    $this->world = SalesWorld::make();
    foreach ($this->world->users as $user) {
        $user->branches()->attach($this->world->branch);
    }
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->world->tenant->id);
    $this->withSession(['branch_id' => $this->world->branch->id]);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('shows the user manual to every signed-in tenant user', function (string $role): void {
    $this->actingAs($this->world->user($role))
        ->get(route('help.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('help/index'));
})->with(['Owner', 'Cashier', 'Stock Keeper']);

it('sends a guest to the login page', function (): void {
    $this->get(route('help.index'))->assertRedirect(route('login'));
});
