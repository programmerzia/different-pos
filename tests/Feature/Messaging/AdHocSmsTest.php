<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Message;
use App\Models\User;
use App\Sms\SendAdHocSms;
use App\Sms\SendSms;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    Queue::fake();
    $this->world = SalesWorld::make(['sms' => ['driver' => 'log', 'cost_per_segment' => 40]]);
    $this->as = function (string $role): User {
        $user = $this->world->user($role);
        if (! $user->branches()->where('branches.id', $this->world->branch->id)->exists()) {
            $user->branches()->attach($this->world->branch);
        }

        return $user;
    };
    $this->vip = CustomerGroup::factory()->create(['tenant_id' => $this->world->tenant->id, 'name' => 'VIP']);
    $this->rahim = $this->world->customer(['name' => 'Rahim', 'phone' => '+8801711000001', 'balance' => Money::ofPaisa(120_000), 'customer_group_id' => $this->vip->id, 'customer_no' => 10000017]);
    $this->karim = $this->world->customer(['name' => 'Karim', 'phone' => '+8801811000002', 'balance' => Money::zero()]);
    $this->world->customer(['name' => 'No Phone', 'phone' => null]);
    $this->world->customer(['name' => 'Gone', 'phone' => '+8801911000003', 'is_active' => false]);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('queues one SMS per reachable customer, filling the variables', function (): void {
    $this->actingAs(($this->as)('Owner'))
        ->post(route('messages.send'), ['target' => 'all', 'body' => 'Hi {name} #{number}, due {due} — {shop}'])
        ->assertRedirect()->assertSessionHasNoErrors();

    Queue::assertPushed(SendSms::class, 2);
    $rows = Message::query()->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('event')->unique()->all())->toBe([SendAdHocSms::EVENT])
        ->and($rows[0]->customer_id)->toBe($this->rahim->id)
        ->and($rows[0]->body)->toBe('Hi Rahim #1000 0017, due 1,200.00 — '.$this->world->tenant->name)
        ->and($rows[1]->phone)->toBe('+8801811000002');
});

it('targets one customer, a group or the customers with a due', function (array $payload, array $names): void {
    $payload = match ($payload['target']) {
        'customer' => [...$payload, 'customer' => $this->karim->uuid],
        'group' => [...$payload, 'group_id' => $this->vip->id],
        default => $payload,
    };

    $this->actingAs(($this->as)('Owner'))->post(route('messages.send'), [...$payload, 'body' => 'Hello'])->assertSessionHasNoErrors();

    expect(Message::query()->with('customer')->get()->map(fn (Message $m) => $m->customer?->name)->all())->toBe($names);
})->with([
    'one customer' => [['target' => 'customer'], ['Karim']],
    'a group' => [['target' => 'group'], ['Rahim']],
    'with a due' => [['target' => 'due'], ['Rahim']],
]);

it('needs settings.manage to send, and to see the composer', function (): void {
    $cashier = ($this->as)('Cashier');

    $this->actingAs($cashier)->post(route('messages.send'), ['target' => 'all', 'body' => 'Hi'])->assertForbidden();
    $this->actingAs($cashier)->get(route('messages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('composer', null)->where('costPerSegment', 40));
    expect(fn () => app(SendAdHocSms::class)->handle($cashier, ['target' => 'all'], 'Hi'))->toThrow(AuthorizationException::class);
    Queue::assertNothingPushed();
});

it('shows the composer with audience counts, templates and the segment cost', function (): void {
    $this->actingAs(($this->as)('Owner'))->get(route('messages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('messages/index')
            ->where('composer.configured', true)
            ->where('composer.cost_per_segment', 40)
            ->where('composer.counts.all', 2)
            ->where('composer.counts.due', 1)
            ->where('composer.groups.0.name', 'VIP')
            ->where('composer.groups.0.count', 1)
            ->has('composer.templates', 3));
});

it('never reaches another tenant\'s customers or groups', function (): void {
    $other = SalesWorld::make(['sms' => ['driver' => 'log']]);
    $foreign = $other->customer(['phone' => '+8801711999999']);
    $foreignGroup = CustomerGroup::factory()->create(['tenant_id' => $other->tenant->id]);
    app(TenantContext::class)->set($this->world->tenant, $this->world->branch);
    $owner = ($this->as)('Owner');

    $this->actingAs($owner)->post(route('messages.send'), ['target' => 'customer', 'customer' => $foreign->uuid, 'body' => 'Hi'])->assertSessionHasErrors('customer');
    $this->actingAs($owner)->post(route('messages.send'), ['target' => 'group', 'group_id' => $foreignGroup->id, 'body' => 'Hi'])->assertSessionHasErrors('group_id');
    $this->actingAs($owner)->post(route('messages.send'), ['target' => 'all', 'body' => 'Hi'])->assertSessionHasNoErrors();

    expect(Message::query()->withoutGlobalScopes()->where('phone', '+8801711999999')->exists())->toBeFalse()
        ->and(Message::query()->count())->toBe(2);
});

it('rejects an empty text, an unknown audience and a tenant without an SMS provider', function (): void {
    $owner = ($this->as)('Owner');

    $this->actingAs($owner)->post(route('messages.send'), ['target' => 'all', 'body' => ''])->assertSessionHasErrors('body');
    $this->actingAs($owner)->post(route('messages.send'), ['target' => 'everyone', 'body' => 'Hi'])->assertSessionHasErrors('target');
    $this->actingAs($owner)->post(route('messages.send'), ['target' => 'customer', 'body' => 'Hi'])->assertSessionHasErrors('customer');

    $tenant = app(TenantContext::class)->tenant();
    $tenant->forceFill(['settings' => [...$tenant->settings, 'sms' => ['driver' => 'null']]])->save();
    $this->actingAs($owner)->post(route('messages.send'), ['target' => 'all', 'body' => 'Hi'])->assertSessionHasErrors('body');

    Queue::assertNothingPushed();
});

it('audits the audience and counts but never the text or a phone number', function (): void {
    $this->actingAs(($this->as)('Owner'))->post(route('messages.send'), ['target' => 'all', 'body' => 'Secret offer {name}']);

    $audit = Activity::query()->where('event', 'sms.ad_hoc_sent')->sole();
    $json = (string) json_encode($audit->properties);
    expect($audit->properties['queued'])->toBe(2)
        ->and($json)->not->toContain('Secret offer')
        ->and($json)->not->toContain('1711000001')
        ->and($json)->not->toContain('Rahim');
});

it('skips customers whose number is not a valid mobile number', function (): void {
    Customer::query()->whereKey($this->karim->id)->update(['phone' => '12345']);

    $result = app(SendAdHocSms::class)->handle(($this->as)('Owner'), ['target' => 'all'], 'Hi');

    expect($result)->toBe(['queued' => 1, 'skipped' => 1]);
});
