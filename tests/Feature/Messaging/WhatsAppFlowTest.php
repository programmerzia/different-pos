<?php

declare(strict_types=1);

use App\Actions\Messaging\SaveWhatsAppSettings;
use App\Actions\Messaging\SendDirectMessage;
use App\Actions\Messaging\SendSmsBroadcast;
use App\Actions\Messaging\SendTestWhatsApp;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Exceptions\Domain\InvalidAdminData;
use App\Exceptions\Domain\WhatsAppNotConfigured;
use App\Messaging\MessageDispatcher;
use App\Models\Activity;
use App\Models\Message;
use App\Models\SmsBroadcast;
use App\Models\User;
use App\Sms\SendSms;
use App\Sms\Templates;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\SalesWorld;

/*
| WhatsApp through the same message log as SMS: SendDirectMessage (dues reminder / thanks, single or selected
| debtors), the composer broadcast on the WhatsApp channel, settings (encrypted token, scrubbed audit), the test
| message, the hub's channel filter and the authorization + tenant isolation of every new endpoint.
*/

function waWorld(array $whatsapp = ['driver' => 'click_to_chat'], array $sms = ['driver' => 'log']): SalesWorld
{
    return SalesWorld::make(['whatsapp' => $whatsapp, 'sms' => $sms, 'phone' => '01711223344']);
}

function branchUser(SalesWorld $world, string $role): User
{
    $user = $world->user($role);
    if (! $user->branches()->where('branches.id', $world->branch->id)->exists()) {
        $user->branches()->attach($world->branch);
    }

    return $user;
}

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('hands a Bangla due reminder to click-to-chat: a handoff row and a wa.me link, nothing queued', function (): void {
    Queue::fake();
    $world = waWorld();
    $world->tenant->forceFill(['phone' => '01711-223344'])->save();
    $rahim = $world->customer(['name' => 'Rahim', 'phone' => '01712345678', 'balance' => Money::ofPaisa(120_000)]);

    $result = app(SendDirectMessage::class)->handle($world->user(), [$rahim], MessageChannel::WhatsApp);

    expect($result['queued'])->toBe(1)->and($result['skipped'])->toBe(0)->and($result['handoff'])->toHaveCount(1);
    $link = $result['handoff'][0];
    expect($link['customer'])->toBe($rahim->uuid)->and($link['phone'])->toBe('+8801712345678')
        ->and($link['url'])->toStartWith('https://wa.me/8801712345678?text=');
    $text = rawurldecode(substr($link['url'], strlen('https://wa.me/8801712345678?text=')));
    expect($text)->toContain('প্রিয় Rahim')->toContain('1,200.00')->toContain($world->tenant->name)->toContain('01711-223344');

    $message = Message::query()->sole();
    expect($message->channel)->toBe(MessageChannel::WhatsApp)->and($message->status)->toBe(MessageStatus::Handoff)
        ->and($message->driver)->toBe('click_to_chat')->and($message->event)->toBe(Templates::DUE_REMINDER)
        ->and($message->body)->toBe($text)->and($message->provider_ref)->toBe('wa.me');
    Queue::assertNothingPushed();
});

it('refuses a bulk click-to-chat reminder truthfully, but queues bulk through the log / Cloud API driver', function (): void {
    Queue::fake();
    $world = waWorld();
    $a = $world->customer(['name' => 'A', 'phone' => '01712345671', 'balance' => Money::ofPaisa(10_000)]);
    $b = $world->customer(['name' => 'B', 'phone' => '01712345672', 'balance' => Money::ofPaisa(20_000)]);
    $clear = $world->customer(['name' => 'Clear', 'phone' => '01712345673', 'balance' => Money::zero()]);

    expect(fn () => app(SendDirectMessage::class)->handle($world->user(), [$a, $b], MessageChannel::WhatsApp))
        ->toThrow(WhatsAppNotConfigured::class, (string) __('sms.whatsapp.bulk_needs_api'));
    expect(Message::query()->count())->toBe(0);

    $world->tenant->forceFill(['settings' => [...$world->tenant->settings, 'whatsapp' => ['driver' => 'log']]])->save();
    app(TenantContext::class)->set($world->tenant->refresh(), $world->branch);

    $result = app(SendDirectMessage::class)->handle($world->user(), [$a, $b, $clear, $world->walkIn], MessageChannel::WhatsApp, Templates::DUE_REMINDER, 'Hi {name}, due {due}. {shop}');

    expect($result)->toBe(['queued' => 2, 'skipped' => 2, 'handoff' => []]);
    expect(Message::query()->where('channel', 'whatsapp')->where('status', 'queued')->count())->toBe(2);
    expect(Message::query()->where('customer_id', $a->id)->sole()->body)->toBe('Hi A, due 100.00. '.$world->tenant->name);
    Queue::assertPushed(SendSms::class, 2);
});

it('sends a thank-you with the collected amount and the last invoice number', function (): void {
    Queue::fake();
    $world = waWorld(['driver' => 'log']);
    $product = $world->product(150_000, 60_000);
    $world->stock($product, 5, 60_000);
    $karim = $world->customer(['name' => 'Karim', 'phone' => '01812345678']);
    $sale = $world->sell([$world->line($product, 1)], [], $karim);
    $karim->refresh();

    $result = app(SendDirectMessage::class)->handle($world->user(), [$karim], MessageChannel::WhatsApp, Templates::PAYMENT_RECEIVED,
        'Thanks {name}: {amount} received for {last_invoice}; due {due}.', ['amount' => '500.00']);

    expect($result['queued'])->toBe(1);
    expect(Message::query()->where('event', Templates::PAYMENT_RECEIVED)->sole()->body)->toBe('Thanks Karim: 500.00 received for '.$sale->number.'; due 1,500.00.');
});

it('refuses WhatsApp without a provider, an unknown event, too many customers and without sms.send', function (): void {
    $world = waWorld(['driver' => 'null']);
    $c = $world->customer(['phone' => '01712345678', 'balance' => Money::ofPaisa(1000)]);

    expect(fn () => app(SendDirectMessage::class)->handle($world->user(), [$c], MessageChannel::WhatsApp))->toThrow(WhatsAppNotConfigured::class);
    expect(fn () => app(SendDirectMessage::class)->handle($world->user(), [$c], MessageChannel::Sms, 'bogus'))->toThrow(InvalidAdminData::class);
    expect(fn () => app(SendDirectMessage::class)->handle($world->user(), [$c], MessageChannel::Sms, 'manual'))->toThrow(InvalidAdminData::class, (string) __('sms.broadcast.body_required'));
    expect(fn () => app(SendDirectMessage::class)->handle($world->user(), array_fill(0, SendDirectMessage::MAX_RECIPIENTS + 1, $c), MessageChannel::Sms))->toThrow(InvalidAdminData::class);
    expect(fn () => app(SendDirectMessage::class)->handle($world->user('Cashier'), [$c], MessageChannel::WhatsApp))->toThrow(AuthorizationException::class);
});

it('POST messages/direct returns the handoff link as JSON, 422 for domain errors, 403/404 across roles and tenants', function (): void {
    Queue::fake();
    $world = waWorld();
    $rahim = $world->customer(['name' => 'Rahim', 'phone' => '01712345678', 'balance' => Money::ofPaisa(120_000)]);
    $owner = branchUser($world, 'Owner');

    $response = $this->actingAs($owner)->postJson(route('messages.direct'), ['channel' => 'whatsapp', 'event' => 'due_reminder', 'customers' => [$rahim->uuid]]);
    $response->assertOk()->assertJsonPath('queued', 1)->assertJsonPath('handoff.0.customer', $rahim->uuid);
    expect($response->json('handoff.0.url'))->toStartWith('https://wa.me/8801712345678?text=');

    $karim = $world->customer(['name' => 'Karim', 'phone' => '01812345678', 'balance' => Money::ofPaisa(5_000)]);
    $this->actingAs($owner)->postJson(route('messages.direct'), ['channel' => 'whatsapp', 'event' => 'due_reminder', 'customers' => [$rahim->uuid, $karim->uuid]])
        ->assertStatus(422)->assertJsonPath('message', (string) __('sms.whatsapp.bulk_needs_api'));

    $this->actingAs($owner)->postJson(route('messages.direct'), ['channel' => 'telegram', 'event' => 'due_reminder', 'customers' => [$rahim->uuid]])->assertStatus(422);
    $this->actingAs(branchUser($world, 'Cashier'))->postJson(route('messages.direct'), ['channel' => 'sms', 'event' => 'due_reminder', 'customers' => [$rahim->uuid]])->assertForbidden();

    $other = SalesWorld::make();
    $foreign = $other->customer(['phone' => '01912345678', 'balance' => Money::ofPaisa(9_000)]);
    app(TenantContext::class)->set($world->tenant->refresh(), $world->branch);
    // A foreign uuid fails the tenant-scoped exists rule: nothing is sent and nothing leaks.
    $this->actingAs($owner)->postJson(route('messages.direct'), ['channel' => 'sms', 'event' => 'due_reminder', 'customers' => [$foreign->uuid]])
        ->assertStatus(422)->assertJsonValidationErrors(['customers.0']);
    expect(Message::query()->where('customer_id', $foreign->id)->exists())->toBeFalse();
});

it('broadcasts from the composer on the WhatsApp channel with no SMS cost, and refuses click-to-chat bulk', function (): void {
    Queue::fake([SendSms::class]);
    $world = waWorld(['driver' => 'log'], ['driver' => 'null']);
    $world->customer(['name' => 'Rahim', 'phone' => '01712345678', 'balance' => Money::ofPaisa(120_000)]);
    $world->customer(['name' => 'Karim', 'phone' => '01812345678', 'balance' => Money::ofPaisa(20_000)]);

    $result = app(SendSmsBroadcast::class)->handle($world->user(), ['target' => 'due'], 'Dear {name}, due {due}.', (string) Str::uuid(), null, MessageChannel::WhatsApp);

    $broadcast = $result['broadcast'];
    expect($result['created'])->toBeTrue()->and($broadcast->channel)->toBe(MessageChannel::WhatsApp)
        ->and($broadcast->recipient_count)->toBe(2)->and($broadcast->cost_paisa->paisa)->toBe(0);
    expect(Message::query()->where('channel', 'whatsapp')->where('related_type', $broadcast->getMorphClass())->count())->toBe(2);
    expect(Activity::query()->where('event', 'sms.broadcast_queued')->sole()->properties->get('channel'))->toBe('whatsapp');

    $world->tenant->forceFill(['settings' => [...$world->tenant->settings, 'whatsapp' => ['driver' => 'click_to_chat']]])->save();
    app(TenantContext::class)->set($world->tenant->refresh(), $world->branch);
    expect(fn () => app(SendSmsBroadcast::class)->handle($world->user(), ['target' => 'due'], 'x', (string) Str::uuid(), null, MessageChannel::WhatsApp))
        ->toThrow(WhatsAppNotConfigured::class);

    $owner = branchUser($world, 'Owner');
    $this->actingAs($owner)->post(route('messages.send'), ['channel' => 'whatsapp', 'target' => 'due', 'body' => 'x', 'idempotency_key' => (string) Str::uuid()])
        ->assertSessionHasErrors('body');
    expect(SmsBroadcast::query()->count())->toBe(1);
});

it('saves the WhatsApp settings with an encrypted token that never reaches the page or the audit', function (): void {
    $world = waWorld(['driver' => 'null']);
    $owner = branchUser($world, 'Owner');

    $tenant = app(SaveWhatsAppSettings::class)->handle($owner, ['driver' => 'meta_cloud', 'phone_number_id' => '1098765', 'access_token' => 'EAAB-secret', 'template_name' => 'due_notice', 'template_language' => 'BN']);

    $stored = $tenant->settings['whatsapp'];
    expect($stored['access_token'])->not->toBe('EAAB-secret')->and(Crypt::decryptString($stored['access_token']))->toBe('EAAB-secret')
        ->and($stored['template_language'])->toBe('bn');
    $audit = Activity::query()->where('event', 'whatsapp.settings_updated')->sole();
    expect(json_encode($audit->properties))->not->toContain('EAAB-secret')->and($audit->properties->get('token_changed'))->toBeTrue();

    // A blank token keeps the stored one; clear_token removes it.
    app(SaveWhatsAppSettings::class)->handle($owner, ['driver' => 'meta_cloud', 'phone_number_id' => '1098765', 'access_token' => '']);
    expect(Crypt::decryptString($world->tenant->refresh()->settings['whatsapp']['access_token']))->toBe('EAAB-secret');
    app(SaveWhatsAppSettings::class)->handle($owner, ['driver' => 'click_to_chat', 'clear_token' => true]);
    expect($world->tenant->refresh()->settings['whatsapp']['access_token'])->toBeNull();

    expect(fn () => app(SaveWhatsAppSettings::class)->handle($world->user('Cashier'), ['driver' => 'log']))->toThrow(AuthorizationException::class);

    $this->actingAs($owner)->get(route('settings.sms.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/sms')
            ->where('whatsapp.driver', 'click_to_chat')->where('whatsapp.status', 'click_to_chat')->where('whatsapp.has_token', false)
            ->missing('whatsapp.access_token'));
});

it('saves both providers from the settings form and sends a test WhatsApp', function (): void {
    $world = waWorld(['driver' => 'null']);
    $owner = branchUser($world, 'Owner');
    $templates = [];
    foreach (Templates::keys() as $key) {
        $templates[$key] = ['enabled' => true, 'body' => Templates::DEFAULTS[$key]];
    }

    $this->actingAs($owner)->put(route('settings.sms.update'), [
        'driver' => 'log', 'templates' => $templates,
        'whatsapp' => ['driver' => 'click_to_chat'],
    ])->assertRedirect(route('settings.sms.edit'));
    expect($world->tenant->refresh()->settings['whatsapp']['driver'])->toBe('click_to_chat');
    $owner->unsetRelation('tenant'); // ResolveTenant reads the user's tenant relation, cached from the first request

    $this->actingAs($owner)->post(route('settings.whatsapp.test'), ['phone' => '০১৭১২৩৪৫৬৭৮'])->assertRedirect(route('settings.sms.edit'));
    $message = Message::query()->where('event', 'test')->sole();
    expect($message->error)->toBeNull();
    expect($message->channel)->toBe(MessageChannel::WhatsApp)->and($message->status)->toBe(MessageStatus::Handoff)->and($message->phone)->toBe('+8801712345678');

    $this->actingAs($owner)->post(route('settings.whatsapp.test'), ['phone' => '123'])->assertSessionHasErrors('whatsapp_phone');
    $this->actingAs(branchUser($world, 'Manager'))->post(route('settings.whatsapp.test'), ['phone' => '01712345678'])->assertForbidden();

    $this->actingAs($owner)->put(route('settings.sms.update'), ['driver' => 'log', 'templates' => $templates, 'whatsapp' => ['driver' => 'telegram']])
        ->assertSessionHasErrors('whatsapp.driver');
});

it('sends the test through the Cloud API with Http faked and records the failure truthfully', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token', 'code' => 190]], 401)]);
    $world = waWorld(['driver' => 'meta_cloud', 'phone_number_id' => '1098765', 'access_token' => Crypt::encryptString('bad')]);

    $result = app(SendTestWhatsApp::class)->handle($world->user(), '01712345678');

    expect($result['link'])->toBeNull()->and($result['message']->status)->toBe(MessageStatus::Failed)
        ->and($result['message']->error)->toContain('Invalid OAuth access token')->and($result['message']->driver)->toBe('meta_cloud');
    Http::assertSentCount(1);
});

it('delivers a queued WhatsApp row through the SendSms job and the dispatcher', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OK']]])]);
    $world = waWorld(['driver' => 'meta_cloud', 'phone_number_id' => '1098765', 'access_token' => Crypt::encryptString('tok')]);
    $message = Message::factory()->create(['tenant_id' => $world->tenant->id, 'channel' => 'whatsapp', 'driver' => 'meta_cloud', 'status' => MessageStatus::Queued, 'body' => 'Hi']);

    (new SendSms($message->id))->handle(app(MessageDispatcher::class));

    expect($message->refresh()->status)->toBe(MessageStatus::Sent)->and($message->provider_ref)->toBe('wamid.OK');
});

it('lists and filters the log by channel, with the truthful WhatsApp status', function (): void {
    $world = waWorld();
    Message::factory()->create(['tenant_id' => $world->tenant->id, 'channel' => 'whatsapp', 'status' => MessageStatus::Handoff, 'body' => 'wa']);
    Message::factory()->create(['tenant_id' => $world->tenant->id, 'channel' => 'sms', 'status' => MessageStatus::Sent, 'body' => 'sms']);
    $other = SalesWorld::make();
    Message::factory()->create(['tenant_id' => $other->tenant->id, 'channel' => 'whatsapp', 'body' => 'theirs']);
    app(TenantContext::class)->set($world->tenant->refresh(), $world->branch);
    $owner = branchUser($world, 'Owner');

    $this->actingAs($owner)->get(route('messages.index', ['filter' => ['channel' => 'whatsapp']]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('messages/index')->where('meta.total', 1)->where('rows.0.body', 'wa')->where('rows.0.channel', 'whatsapp')
            ->where('whatsapp.status', 'click_to_chat')->where('composer.whatsapp.bulk', false));
    $this->actingAs($owner)->get(route('messages.index'))->assertInertia(fn (Assert $page) => $page->where('meta.total', 2));
});

it('exposes the messaging props to the dues pages and the customer profile without secrets', function (): void {
    $world = waWorld(['driver' => 'meta_cloud', 'phone_number_id' => '1', 'access_token' => Crypt::encryptString('s')]);
    $product = $world->product(150_000, 60_000);
    $world->stock($product, 5, 60_000);
    $rahim = $world->customer(['name' => 'Rahim', 'phone' => '01712345678']);
    $world->sell([$world->line($product, 1)], [], $rahim);
    $owner = branchUser($world, 'Owner');

    $this->actingAs($owner)->get(route('dues.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('messaging.whatsapp.status', 'connected')->where('messaging.can_send', true)
            ->where('messaging.defaults.due_reminder', Templates::WHATSAPP_DEFAULTS[Templates::DUE_REMINDER])->missing('messaging.whatsapp.access_token'));
    $this->actingAs($owner)->get(route('dues.show', $rahim->uuid))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('messaging.whatsapp.bulk', true)->where('last_payment', null));
    $this->actingAs($owner)->get(route('customers.show', $rahim->uuid))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('messaging.shop', $world->tenant->name));
});
