<?php

declare(strict_types=1);

use App\Actions\Messaging\ResendMessage;
use App\Actions\Messaging\SendDirectMessage;
use App\Actions\Messaging\SendSmsBroadcast;
use App\Actions\Messaging\SendTestSms;
use App\Enums\MessageStatus;
use App\Exceptions\Domain\SmsNotConfigured;
use App\Messaging\MessageDispatcher;
use App\Models\Activity;
use App\Models\Message;
use App\Sms\SendSms;
use App\Sms\SmsNotice;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\SalesWorld;

/*
| The master SMS switch: off means no customer SMS on any path, with the credentials kept. A test SMS to a
| number the user types is the one exception.
*/

beforeEach(function (): void {
    Queue::fake([SendSms::class]);
    $this->world = SalesWorld::make(['sms' => ['driver' => 'log', 'sending_enabled' => false]]);
    $this->owner = $this->world->user('Owner');
    if (! $this->owner->branches()->where('branches.id', $this->world->branch->id)->exists()) {
        $this->owner->branches()->attach($this->world->branch);
    }
    $this->rahim = $this->world->customer(['name' => 'Rahim', 'phone' => '+8801711000001', 'balance' => Money::ofPaisa(120_000)]);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('is on by default, for a tenant saved before the switch existed', function (): void {
    $tenant = $this->world->tenant;
    $tenant->settings = [...$tenant->settings, 'sms' => ['driver' => 'log']];
    $tenant->save();

    expect(SmsSettings::fromTenant($tenant)->sendingEnabled)->toBeTrue()
        ->and(SmsSettings::fromTenant($tenant)->sends(Templates::SALE_COMPLETED))->toBeTrue();
});

it('stops automatic messages and says so without blocking', function (): void {
    $settings = SmsSettings::fromTenant($this->world->tenant);

    expect($settings->isConfigured())->toBeTrue()
        ->and($settings->sends(Templates::SALE_COMPLETED))->toBeFalse()
        ->and(SmsNotice::missing(Templates::SALE_COMPLETED, $this->rahim))->toBe(__('sms.notice.sending_off'));
});

it('refuses a broadcast and a direct message', function (): void {
    expect(fn () => app(SendSmsBroadcast::class)->handle($this->owner, ['target' => 'due'], 'Hello {name}', str_repeat('k', 20)))
        ->toThrow(SmsNotConfigured::class, __('sms.sending_off'));
    expect(fn () => app(SendDirectMessage::class)->handle($this->owner, [$this->rahim]))
        ->toThrow(SmsNotConfigured::class, __('sms.sending_off'));

    expect(Message::query()->count())->toBe(0);
    Queue::assertNotPushed(SendSms::class);
});

it('stops a message already queued, and refuses to resend it', function (): void {
    $message = SendSms::enqueue(Templates::DUE_REMINDER, $this->rahim->phone, 'Pay', $this->rahim, $this->rahim);

    (new SendSms($message->id))->handle(app(MessageDispatcher::class));

    $message->refresh();
    expect($message->status)->toBe(MessageStatus::Failed)
        ->and($message->error)->toBe(__('sms.sending_off'))
        ->and($message->sent_at)->toBeNull()
        ->and(fn () => app(ResendMessage::class)->handle($this->owner, $message))->toThrow(SmsNotConfigured::class);
});

it('still sends a test SMS to a typed number', function (): void {
    $message = app(SendTestSms::class)->handle($this->owner, '01712345678');

    expect($message->status)->toBe(MessageStatus::Sent);
});

it('switches at once, keeps the credentials and survives saving the form', function (): void {
    $this->actingAs($this->owner)->put(route('settings.sms.sending'), ['enabled' => true])
        ->assertRedirect(route('settings.sms.edit'));
    expect(SmsSettings::fromTenant($this->world->tenant->refresh())->sendingEnabled)->toBeTrue()
        ->and(SmsSettings::fromTenant($this->world->tenant)->driver)->toBe('log')
        ->and(Activity::query()->where('event', 'sms.sending_switched')->count())->toBe(1);

    $this->actingAs($this->owner)->put(route('settings.sms.sending'), ['enabled' => false])->assertRedirect();

    $templates = collect(Templates::DEFAULTS)->map(fn (string $body): array => ['enabled' => true, 'body' => $body])->all();
    $this->actingAs($this->owner)->put(route('settings.sms.update'), ['driver' => 'log', 'templates' => $templates])->assertRedirect();

    expect(SmsSettings::fromTenant($this->world->tenant->refresh())->sendingEnabled)->toBeFalse();
});

it('needs settings permission to switch', function (): void {
    $cashier = $this->world->user('Cashier');
    if (! $cashier->branches()->where('branches.id', $this->world->branch->id)->exists()) {
        $cashier->branches()->attach($this->world->branch);
    }

    $this->actingAs($cashier)->put(route('settings.sms.sending'), ['enabled' => true])->assertForbidden();

    expect(SmsSettings::fromTenant($this->world->tenant->refresh())->sendingEnabled)->toBeFalse();
});
