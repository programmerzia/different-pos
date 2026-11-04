<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\User;
use App\Sms\SendSms;
use App\Sms\Templates;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    $this->world = SalesWorld::make(['sms' => ['driver' => 'log']]);
    $as = function (string $role): User {
        $user = $this->world->user($role);
        if (! $user->branches()->where('branches.id', $this->world->branch->id)->exists()) {
            $user->branches()->attach($this->world->branch);
        }

        return $user;
    };
    $this->as = $as;
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('shows the settings page without ever exposing the token', function (): void {
    $this->actingAs(($this->as)('Owner'))->get(route('settings.sms.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/sms')
            ->where('sms.driver', 'log')->where('sms.has_token', false)
            ->has('sms.templates', 5)->missing('sms.api_token'));
});

it('forbids the settings page, the update and the test SMS without settings.manage', function (): void {
    $cashier = ($this->as)('Cashier');

    $this->actingAs($cashier)->get(route('settings.sms.edit'))->assertForbidden();
    $this->actingAs($cashier)->put(route('settings.sms.update'), ['driver' => 'log'])->assertForbidden();
    $this->actingAs($cashier)->post(route('settings.sms.test'), ['phone' => '01712345678'])->assertForbidden();
});

it('updates the settings', function (): void {
    $templates = [];
    foreach (Templates::DEFAULTS as $key => $body) {
        $templates[$key] = ['enabled' => true, 'body' => $body];
    }

    $this->actingAs(($this->as)('Owner'))->put(route('settings.sms.update'), [
        'driver' => 'ssl_wireless', 'sender_id' => 'SHOP', 'sid' => 'SID', 'api_token' => 'abc', 'templates' => $templates,
    ])->assertRedirect(route('settings.sms.edit'));

    $this->world->tenant->refresh();
    expect($this->world->tenant->settings['sms']['driver'])->toBe('ssl_wireless');

    $this->actingAs(($this->as)('Owner'))->put(route('settings.sms.update'), ['driver' => 'carrier-pigeon', 'templates' => $templates])
        ->assertSessionHasErrors('driver');
});

it('requires a https base url for the onecodesoft driver', function (): void {
    $templates = [];
    foreach (Templates::DEFAULTS as $key => $body) {
        $templates[$key] = ['enabled' => true, 'body' => $body];
    }
    $owner = ($this->as)('Owner');

    $this->actingAs($owner)->put(route('settings.sms.update'), ['driver' => 'onecodesoft', 'base_url' => '', 'templates' => $templates])
        ->assertSessionHasErrors('base_url');
    $this->actingAs($owner)->put(route('settings.sms.update'), ['driver' => 'onecodesoft', 'base_url' => 'http://acct.example.test', 'templates' => $templates])
        ->assertSessionHasErrors('base_url');
    $this->actingAs($owner)->put(route('settings.sms.update'), ['driver' => 'onecodesoft', 'base_url' => 'https://acct.example.test', 'templates' => $templates])
        ->assertRedirect(route('settings.sms.edit'));
});

it('sends a test SMS and rejects a bad number', function (): void {
    $owner = ($this->as)('Owner');

    $this->actingAs($owner)->post(route('settings.sms.test'), ['phone' => '01712345678'])->assertRedirect(route('settings.sms.edit'));
    expect(Message::query()->where('event', 'test')->sole()->status)->toBe(MessageStatus::Sent);

    $this->actingAs($owner)->post(route('settings.sms.test'), ['phone' => '123'])->assertSessionHasErrors('phone');
});

it('lists the log with a status filter, scoped to the tenant', function (): void {
    Message::factory()->create(['tenant_id' => $this->world->tenant->id, 'status' => MessageStatus::Failed, 'body' => 'mine-failed']);
    Message::factory()->create(['tenant_id' => $this->world->tenant->id, 'status' => MessageStatus::Sent, 'body' => 'mine-sent']);
    $other = SalesWorld::make();
    Message::factory()->create(['tenant_id' => $other->tenant->id, 'body' => 'theirs']);
    $this->world->tenant->refresh();
    app(TenantContext::class)->set($this->world->tenant, $this->world->branch);
    $owner = ($this->as)('Owner');

    $this->actingAs($owner)->get(route('messages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('messages/index')->where('meta.total', 2)->where('canResend', true));
    $this->actingAs($owner)->get(route('messages.index', ['filter' => ['status' => 'failed']]))
        ->assertInertia(fn (Assert $page) => $page->where('meta.total', 1)->where('rows.0.body', 'mine-failed'));
});

it('shows the log to sms.view holders but hides resend without sms.send; others are forbidden', function (): void {
    $this->actingAs(($this->as)('Accountant'))->get(route('messages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canResend', false));
    $this->actingAs(($this->as)('Manager'))->get(route('messages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canResend', true));
    // Customer phones and texts: a cashier (customers.view only) no longer sees the log.
    $this->actingAs(($this->as)('Cashier'))->get(route('messages.index'))->assertForbidden();
    $this->actingAs(($this->as)('Stock Keeper'))->get(route('messages.index'))->assertForbidden();
});

it('resends a failed message and returns 404 across tenants', function (): void {
    Queue::fake();
    $failed = Message::factory()->create(['tenant_id' => $this->world->tenant->id, 'status' => MessageStatus::Failed]);
    $owner = ($this->as)('Owner');

    $this->actingAs($owner)->post(route('messages.resend', $failed))->assertRedirect();
    expect($failed->refresh()->status)->toBe(MessageStatus::Queued);
    Queue::assertPushed(SendSms::class);

    $other = SalesWorld::make();
    $foreign = Message::factory()->create(['tenant_id' => $other->tenant->id, 'status' => MessageStatus::Failed]);
    app(TenantContext::class)->set($this->world->tenant, $this->world->branch);
    $this->actingAs($owner)->post(route('messages.resend', $foreign))->assertNotFound();
    $this->actingAs(($this->as)('Cashier'))->post(route('messages.resend', $failed))->assertForbidden();
});
