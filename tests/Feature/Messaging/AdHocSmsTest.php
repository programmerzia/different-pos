<?php

declare(strict_types=1);

use App\Actions\Messaging\SendSmsBroadcast;
use App\Enums\SmsBroadcastStatus;
use App\Exceptions\Domain\InvalidAdminData;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Message;
use App\Models\SmsBroadcast;
use App\Models\User;
use App\Sms\BroadcastAudience;
use App\Sms\FanOutSmsBroadcast;
use App\Sms\SendSms;
use App\Sms\Templates;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\SalesWorld;

/*
| The composer's ad-hoc SMS: SendSmsBroadcast records one sms_broadcasts row plus its audit and queues
| one FanOutSmsBroadcast job, which writes the per-recipient messages and SendSms jobs in chunks. The
| queue is faked for SendSms only, so the fan-out runs (sync, after commit) and the sends are recorded.
*/

beforeEach(function (): void {
    Queue::fake([SendSms::class]);
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
    $this->send = fn (array $payload, ?User $user = null) => $this->actingAs($user ?? ($this->as)('Owner'))
        ->post(route('messages.send'), ['idempotency_key' => (string) Str::uuid(), ...$payload]);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('records one broadcast and fans it out to one SMS per reachable customer, filling the variables', function (): void {
    ($this->send)(['target' => 'all', 'body' => 'Hi {name} #{number}, due {due} — {shop}'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $broadcast = SmsBroadcast::query()->sole();
    expect($broadcast->status)->toBe(SmsBroadcastStatus::Completed)
        ->and($broadcast->recipient_count)->toBe(2)
        ->and($broadcast->queued_count)->toBe(2)
        ->and($broadcast->skipped_count)->toBe(0)
        ->and($broadcast->cost_paisa->paisa)->toBe(2 * 40)
        ->and($broadcast->audience)->toBe(['target' => 'all', 'customer' => null, 'group_id' => null]);

    Queue::assertPushed(SendSms::class, 2);
    $rows = Message::query()->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('event')->unique()->all())->toBe([BroadcastAudience::EVENT])
        ->and($rows->pluck('related_id')->unique()->all())->toBe([$broadcast->id])
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

    ($this->send)([...$payload, 'body' => 'Hello'])->assertSessionHasNoErrors();

    expect(Message::query()->with('customer')->get()->map(fn (Message $m) => $m->customer?->name)->all())->toBe($names);
})->with([
    'one customer' => [['target' => 'customer'], ['Karim']],
    'a group' => [['target' => 'group'], ['Rahim']],
    'with a due' => [['target' => 'due'], ['Rahim']],
]);

it('returns the existing broadcast for a repeated idempotency key and sends nothing again', function (): void {
    $key = (string) Str::uuid();

    ($this->send)(['target' => 'all', 'body' => 'Sale today', 'idempotency_key' => $key])->assertSessionHasNoErrors();
    ($this->send)(['target' => 'all', 'body' => 'Sale today', 'idempotency_key' => $key])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'warning');

    expect(SmsBroadcast::query()->count())->toBe(1)
        ->and(Message::query()->count())->toBe(2)
        ->and(Activity::query()->where('event', 'sms.broadcast_queued')->count())->toBe(1);
    Queue::assertPushed(SendSms::class, 2);

    $again = app(SendSmsBroadcast::class)->handle(($this->as)('Owner'), ['target' => 'all'], 'Sale today', $key);
    expect($again['created'])->toBeFalse()->and($again['broadcast']->id)->toBe(SmsBroadcast::query()->sole()->id);
});

it('fans out in chunks and resumes without duplicates after a crash part-way', function (): void {
    for ($i = 0; $i < FanOutSmsBroadcast::CHUNK + 5; $i++) {
        $this->world->customer(['phone' => sprintf('+88017%08d', 20000000 + $i)]);
    }
    $total = FanOutSmsBroadcast::CHUNK + 5 + 2;
    Queue::fake([SendSms::class, FanOutSmsBroadcast::class]);

    $result = app(SendSmsBroadcast::class)->handle(($this->as)('Owner'), ['target' => 'all'], 'Hi {name}', (string) Str::uuid());
    $broadcast = $result['broadcast'];
    expect($broadcast->recipient_count)->toBe($total)->and(Message::query()->count())->toBe(0);
    Queue::assertPushed(FanOutSmsBroadcast::class, 1);

    // Simulate a first attempt that wrote the first chunk, then died before finishing.
    $first = Customer::query()->whereIn('id', app(BroadcastAudience::class)->recipients(['target' => 'all'])->select('id'))->orderBy('id')->limit(FanOutSmsBroadcast::CHUNK)->get();
    foreach ($first as $customer) {
        SendSms::enqueue(BroadcastAudience::EVENT, $customer->phone, 'Hi', $customer, $broadcast);
    }
    $broadcast->forceFill(['status' => SmsBroadcastStatus::Sending, 'queued_count' => FanOutSmsBroadcast::CHUNK])->save();

    (new FanOutSmsBroadcast($broadcast->id))->handle(app(BroadcastAudience::class));
    (new FanOutSmsBroadcast($broadcast->id))->handle(app(BroadcastAudience::class)); // a late duplicate delivery

    $broadcast->refresh();
    expect(Message::query()->count())->toBe($total)
        ->and(Message::query()->distinct()->count('customer_id'))->toBe($total)
        ->and($broadcast->status)->toBe(SmsBroadcastStatus::Completed)
        ->and($broadcast->queued_count)->toBe($total);
});

it('needs sms.send to send, and to see the composer (sms.view alone shows the log)', function (): void {
    $cashier = ($this->as)('Accountant'); // sms.view without sms.send

    ($this->send)(['target' => 'all', 'body' => 'Hi'], $cashier)->assertForbidden();
    $this->actingAs($cashier)->get(route('messages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('composer', null)->where('costPerSegment', 40));
    expect(fn () => app(SendSmsBroadcast::class)->handle($cashier, ['target' => 'all'], 'Hi', (string) Str::uuid()))->toThrow(AuthorizationException::class);
    Queue::assertNothingPushed();
    expect(SmsBroadcast::query()->count())->toBe(0);
});

it('shows the composer with audience counts of valid mobile numbers only, templates and the segment cost', function (): void {
    $this->world->customer(['name' => 'Landline', 'customer_group_id' => $this->vip->id]);
    Customer::query()->where('name', 'Landline')->update(['phone' => '029876543']);

    $this->actingAs(($this->as)('Owner'))->get(route('messages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('messages/index')
            ->where('composer.configured', true)
            ->where('composer.cost_per_segment', 40)
            ->where('composer.counts.all', 2)
            ->where('composer.counts.due', 1)
            ->where('composer.groups.0.name', 'VIP')
            ->where('composer.groups.0.count', 1)
            ->has('composer.templates', 5));
});

it('never reaches another tenant\'s customers, groups or broadcasts', function (): void {
    $other = SalesWorld::make(['sms' => ['driver' => 'log']]);
    $foreign = $other->customer(['phone' => '+8801711999999']);
    $foreignGroup = CustomerGroup::factory()->create(['tenant_id' => $other->tenant->id]);
    $key = (string) Str::uuid();
    $foreignBroadcast = app(TenantContext::class)->run($other->tenant, $other->branch, fn (): SmsBroadcast => app(SendSmsBroadcast::class)
        ->handle($other->user('Owner'), ['target' => 'all'], 'Theirs', $key)['broadcast']);
    app(TenantContext::class)->set($this->world->tenant, $this->world->branch);
    $owner = ($this->as)('Owner');

    ($this->send)(['target' => 'customer', 'customer' => $foreign->uuid, 'body' => 'Hi'], $owner)->assertSessionHasErrors('customer');
    ($this->send)(['target' => 'group', 'group_id' => $foreignGroup->id, 'body' => 'Hi'], $owner)->assertSessionHasErrors('group_id');
    // The same key in another tenant is a different broadcast, not the foreign one.
    ($this->send)(['target' => 'all', 'body' => 'Hi', 'idempotency_key' => $key], $owner)->assertSessionHasNoErrors();

    expect(SmsBroadcast::query()->pluck('body')->all())->toBe(['Hi'])
        ->and(SmsBroadcast::query()->find($foreignBroadcast->id))->toBeNull()
        ->and(Message::query()->where('phone', '+8801711999999')->exists())->toBeFalse()
        ->and(Message::query()->count())->toBe(2);
});

it('rejects an empty text, an unknown audience, a missing key and a tenant without an SMS provider', function (): void {
    $owner = ($this->as)('Owner');

    ($this->send)(['target' => 'all', 'body' => ''], $owner)->assertSessionHasErrors('body');
    ($this->send)(['target' => 'everyone', 'body' => 'Hi'], $owner)->assertSessionHasErrors('target');
    ($this->send)(['target' => 'customer', 'body' => 'Hi'], $owner)->assertSessionHasErrors('customer');
    ($this->send)(['target' => 'all', 'body' => 'Hi', 'idempotency_key' => ''], $owner)->assertSessionHasErrors('idempotency_key');

    $tenant = app(TenantContext::class)->tenant();
    $tenant->forceFill(['settings' => [...$tenant->settings, 'sms' => ['driver' => 'null']]])->save();
    ($this->send)(['target' => 'all', 'body' => 'Hi'], $owner)->assertSessionHasErrors('body');

    Queue::assertNothingPushed();
    expect(SmsBroadcast::query()->count())->toBe(0);
});

it('rejects an audience with no valid mobile number, in the user\'s language', function (): void {
    Customer::query()->whereKey($this->karim->id)->update(['phone' => '12345']);
    app()->setLocale('bn');

    expect(fn () => app(SendSmsBroadcast::class)->handle(($this->as)('Owner'), ['target' => 'customer', 'customer' => $this->karim->uuid], 'Hi', (string) Str::uuid()))
        ->toThrow(InvalidAdminData::class, (string) __('sms.ad_hoc_none', [], 'bn'))
        ->and(fn () => app(SendSmsBroadcast::class)->handle(($this->as)('Owner'), ['target' => 'all'], '  ', (string) Str::uuid()))
        ->toThrow(InvalidAdminData::class, 'আগে বার্তাটি লিখুন।');

    $result = app(SendSmsBroadcast::class)->handle(($this->as)('Owner'), ['target' => 'all'], 'Hi', (string) Str::uuid());
    expect($result['broadcast']->recipient_count)->toBe(1)
        ->and(Message::query()->pluck('customer_id')->all())->toBe([$this->rahim->id]);
});

it('audits the broadcast and its fan-out with the audience and counts but never the text or a phone number', function (): void {
    ($this->send)(['target' => 'all', 'body' => 'Secret offer {name}']);

    $queued = Activity::query()->where('event', 'sms.broadcast_queued')->sole();
    $sent = Activity::query()->where('event', 'sms.broadcast_sent')->sole();
    $json = json_encode($queued->properties).json_encode($sent->properties);
    expect($queued->properties['recipients'])->toBe(2)
        ->and($queued->subject_id)->toBe(SmsBroadcast::query()->sole()->id)
        ->and($sent->properties['queued'])->toBe(2)
        ->and($json)->not->toContain('Secret offer')
        ->and($json)->not->toContain('1711000001')
        ->and($json)->not->toContain('Rahim');
});

it('refuses a broadcast with details it cannot fill, so nobody receives raw placeholders', function (string $body): void {
    $user = ($this->as)('Owner');

    expect(fn () => app(SendSmsBroadcast::class)->handle($user, ['target' => 'due'], $body, str_repeat('k', 20)))
        ->toThrow(InvalidAdminData::class, '{');

    expect(SmsBroadcast::query()->count())->toBe(0)->and(Message::query()->count())->toBe(0);
    Queue::assertNotPushed(SendSms::class);
})->with([
    'the sale template' => [Templates::DEFAULTS[Templates::SALE_COMPLETED]],
    'a typed placeholder' => ['Hello {name}, your {invoice} is ready'],
]);

it('sends a due reminder template by broadcast with every detail filled', function (): void {
    $user = ($this->as)('Owner');

    app(SendSmsBroadcast::class)->handle($user, ['target' => 'due'], Templates::DEFAULTS[Templates::DUE_REMINDER], str_repeat('k', 20));

    $body = (string) Message::query()->where('customer_id', $this->rahim->id)->sole()->body;
    expect($body)->toContain('Rahim')->toContain('1,200.00')->not->toContain('{');
});

it('marks which templates the composer can offer for a broadcast', function (): void {
    $user = ($this->as)('Owner');
    $templates = collect($this->actingAs($user)->get('/app/messages')->assertOk()->viewData('page')['props']['composer']['templates'])->keyBy('key');

    expect($templates['due_reminder']['broadcastable'])->toBeTrue()
        ->and($templates['sale_completed']['broadcastable'])->toBeFalse()
        ->and($templates['payment_received']['broadcastable'])->toBeFalse()
        ->and($templates['sale_returned']['broadcastable'])->toBeFalse()
        ->and($templates['sale_voided']['broadcastable'])->toBeFalse();
});

it('refuses a template saved with a detail it cannot fill', function (): void {
    $user = ($this->as)('Owner');
    $templates = collect(Templates::DEFAULTS)->map(fn (string $body): array => ['enabled' => true, 'body' => $body])->all();
    $templates[Templates::PAYMENT_RECEIVED]['body'] = 'Paid for {invoice}';

    $this->actingAs($user)->put(route('settings.sms.update'), ['driver' => 'log', 'sender_id' => '', 'templates' => $templates])
        ->assertSessionHasErrors('templates.payment_received.body');
});
