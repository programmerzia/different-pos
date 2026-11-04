<?php

declare(strict_types=1);

require_once __DIR__.'/AdminHelpers.php';

use App\Sms\Templates;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('renders every settings hub section for the owner', function (string $url, string $component): void {
    [$owner] = adminActor();

    $this->actingAs($owner)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'business' => ['/app/settings/business', 'settings/business'],
    'branches' => ['/app/settings/branches', 'settings/branches'],
    'registers' => ['/app/settings/registers', 'settings/registers'],
    'payment methods' => ['/app/settings/payment-methods', 'settings/payment-methods'],
    'couriers' => ['/app/settings/couriers', 'settings/couriers'],
    'taxes' => ['/app/settings/taxes', 'settings/taxes'],
    'units' => ['/app/settings/units', 'settings/units'],
    'sms' => ['/app/settings/sms', 'settings/sms'],
]);

it('keeps the hub sections behind settings.manage', function (string $url): void {
    [$cashier] = adminActor(SystemRoles::CASHIER);

    $this->actingAs($cashier)->get($url)->assertForbidden();
})->with(['/app/settings/business', '/app/settings/branches', '/app/settings/sms', '/app/settings/units']);

it('saves the SMS price per part and keeps it when the field is left out', function (): void {
    [$owner, $tenant] = adminActor();
    $templates = [];
    foreach (Templates::DEFAULTS as $key => $body) {
        $templates[$key] = ['enabled' => true, 'body' => $body];
    }

    $this->actingAs($owner)->put('/app/settings/sms', ['driver' => 'log', 'cost_per_segment' => 50, 'templates' => $templates])->assertRedirect();
    expect($tenant->refresh()->settings['sms']['cost_per_segment'])->toBe(50);
    $owner->unsetRelation('tenant'); // a real request loads the tenant afresh; the test reuses the user model
    $this->get('/app/settings/sms')->assertInertia(fn (Assert $page) => $page->where('sms.cost_per_segment', 50));

    $this->put('/app/settings/sms', ['driver' => 'log', 'templates' => $templates])->assertRedirect();
    expect($tenant->refresh()->settings['sms']['cost_per_segment'])->toBe(50);

    $this->put('/app/settings/sms', ['driver' => 'log', 'cost_per_segment' => -1, 'templates' => $templates])->assertSessionHasErrors('cost_per_segment');
});
