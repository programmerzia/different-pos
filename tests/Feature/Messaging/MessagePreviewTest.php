<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Sms\MessageContext;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Tests\Fixtures\SalesWorld;

/* GET /app/messages/preview: the composer's live template values for one customer (same source as the send). */

beforeEach(function (): void {
    $this->world = SalesWorld::make(['sms' => ['driver' => 'log', 'cost_per_segment' => 40]]);
    $this->as = function (string $role): User {
        $user = $this->world->user($role);
        if (! $user->branches()->where('branches.id', $this->world->branch->id)->exists()) {
            $user->branches()->attach($this->world->branch);
        }

        return $user;
    };
    $this->roman = $this->world->customer(['name' => 'Roman', 'phone' => '+8801711000001', 'balance' => Money::ofPaisa(850_000), 'customer_no' => 10000017]);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('returns the live due, name aliases, shop and number of the customer', function (): void {
    $this->actingAs(($this->as)('Owner'))
        ->getJson(route('messages.preview', ['customer' => $this->roman->uuid]))
        ->assertOk()
        ->assertJsonPath('values.name', 'Roman')
        ->assertJsonPath('values.customer', 'Roman')
        ->assertJsonPath('values.due', '8,500.00')
        ->assertJsonPath('values.shop', $this->world->tenant->name)
        ->assertJsonPath('values.number', '1000 0017');
});

it('fills {name} as an alias of {customer} in transactional templates', function (): void {
    $values = app(MessageContext::class)->values($this->roman);

    expect($values['name'])->toBe('Roman')->and($values['customer'])->toBe('Roman');
});

it('needs sms.send', function (): void {
    $this->actingAs(($this->as)('Cashier'))
        ->getJson(route('messages.preview', ['customer' => $this->roman->uuid]))
        ->assertForbidden();
});

it('does not reveal a customer of another tenant', function (): void {
    $foreign = app(TenantContext::class)->withoutTenancy(fn (): Customer => Customer::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id, 'phone' => '+8801711000009',
    ]));

    $this->actingAs(($this->as)('Owner'))
        ->getJson(route('messages.preview', ['customer' => $foreign->uuid]))
        ->assertNotFound();
});
