<?php

declare(strict_types=1);

use App\Actions\Messaging\ResendMessage;
use App\Actions\Messaging\SaveSmsSettings;
use App\Actions\Messaging\SendDueReminder;
use App\Actions\Messaging\SendTestSms;
use App\Actions\Sales\CollectCustomerPayment;
use App\Actions\Sales\Data\CustomerPaymentDraft;
use App\Enums\MessageStatus;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\SmsNotConfigured;
use App\Models\Message;
use App\Models\Product;
use App\Models\Tenant;
use App\Sms\SendCustomerStatement;
use App\Sms\SendSms;
use App\Sms\SmsSender;
use App\Sms\Templates;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    $this->travelTo(now()->setTimezone('Asia/Dhaka')->setDate(2026, 11, 3)->setTime(14, 0)->utc());
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

function shirt(SalesWorld $world): Product
{
    return $GLOBALS['sms_shirt'][$world->tenant->id];
}

function smsWorld(array $sms = ['driver' => 'log']): SalesWorld
{
    $world = SalesWorld::make(['sms' => $sms]);
    $product = $world->product(150_000, 60_000);
    $world->stock($product, 20, 60_000);
    $GLOBALS['sms_shirt'][$world->tenant->id] = $product;

    return $world;
}

it('queues one SendSms with the rendered body for a named customer', function (): void {
    Queue::fake();
    $world = smsWorld();
    $customer = $world->customer(['name' => 'Rahim', 'phone' => '01712345678']);

    $sale = $world->sell([$world->line(shirt($world), 1)], [$world->tender('cash', 100_000)], $customer);

    Queue::assertPushed(SendSms::class, 1);
    $message = Message::query()->sole();
    expect($message->body)->toBe('Thank you Rahim! Invoice '.$sale->number.' total ৳1,500.00, paid ৳1,000.00, due ৳500.00. — '.$world->tenant->name)
        ->and($message->phone)->toBe('+8801712345678')
        ->and($message->status)->toBe(MessageStatus::Queued)
        ->and($message->tenant_id)->toBe($world->tenant->id)
        ->and($message->customer_id)->toBe($customer->id)
        ->and($message->event)->toBe('sale_completed');
});

it('queues nothing for a walk-in sale, a customer without a phone, a disabled template or no provider', function (): void {
    Queue::fake();
    $world = smsWorld();
    $world->sell([$world->line(shirt($world), 1)], [$world->tender('cash', 150_000)]);
    $world->sell([$world->line(shirt($world), 1)], [$world->tender('cash', 150_000)], $world->customer(['phone' => null]));
    expect(Message::query()->count())->toBe(0);

    $off = smsWorld(['driver' => 'log', 'templates' => ['sale_completed' => ['enabled' => false, 'body' => 'x']]]);
    $off->sell([$off->line(shirt($off), 1)], [$off->tender('cash', 150_000)], $off->customer(['phone' => '01712345678']));
    $none = smsWorld(['driver' => 'null']);
    $none->sell([$none->line(shirt($none), 1)], [$none->tender('cash', 150_000)], $none->customer(['phone' => '01712345678']));

    Queue::assertNothingPushed();
});

it('sends a collected payment as a payment-received SMS', function (): void {
    Queue::fake();
    $world = smsWorld();
    $customer = $world->customer(['name' => 'Karim', 'phone' => '01812345678']);
    $world->sell([$world->line(shirt($world), 1)], [], $customer);
    Message::query()->delete();

    app(CollectCustomerPayment::class)->handle($world->user(), new CustomerPaymentDraft($customer->id, Money::ofPaisa(50_000), $world->methods['cash']->id));

    $message = Message::query()->sole();
    expect($message->event)->toBe('payment_received')
        ->and($message->body)->toBe('Dear Karim, we received ৳500.00. Remaining due ৳1,000.00. — '.$world->tenant->name);
});

it('the job sends through the driver, records the reference and runs under the tenant context', function (): void {
    Http::fake(['*' => Http::response(['status' => 'SUCCESS', 'smsinfo' => [['reference_id' => 'REF-1']]])]);
    $world = smsWorld(['driver' => 'ssl_wireless', 'sid' => 'SHOP', 'api_token' => Crypt::encryptString('T')]);
    $customer = $world->customer(['phone' => '01712345678']);
    $message = SendSms::enqueue('manual', $customer->phone, 'Hello', $customer);
    expect($message?->refresh()->status)->toBe(MessageStatus::Sent);

    // QUEUE_CONNECTION=sync already ran it; run it again from a clean context to prove the middleware restores it.
    $message->update(['status' => MessageStatus::Queued, 'provider_ref' => null]);
    $tenantId = $world->tenant->id;
    app(TenantContext::class)->forget();
    Context::add('tenant_id', $tenantId);
    SendSms::dispatchSync($message?->id ?? 0);

    $fresh = app(TenantContext::class)->withoutTenancy(fn () => Message::query()->findOrFail($message?->id));
    expect($fresh->status)->toBe(MessageStatus::Sent)
        ->and($fresh->provider_ref)->toBe('REF-1')
        ->and($fresh->tenant_id)->toBe($tenantId)
        ->and($fresh->sent_at)->not->toBeNull();
});

it('marks a rejected message failed with the error', function (): void {
    Http::fake(['*' => Http::response(['status' => 'FAILED', 'error_message' => 'Invalid SID'])]);
    $world = smsWorld(['driver' => 'ssl_wireless', 'sid' => 'BAD', 'api_token' => Crypt::encryptString('T')]);

    $message = SendSms::enqueue('manual', '01712345678', 'Hello');

    expect($message?->refresh()->status)->toBe(MessageStatus::Failed)->and($message?->error)->toBe('Invalid SID');
});

it('a timeout stays queued while retries remain and fails on the last attempt', function (): void {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    $world = smsWorld(['driver' => 'ssl_wireless', 'sid' => 'S', 'api_token' => Crypt::encryptString('T')]);
    Queue::fake();
    $message = SendSms::enqueue('manual', '01712345678', 'Hello');
    $sender = app(SmsSender::class);

    $sender->deliver($message ?? throw new RuntimeException, final: false);
    expect($message->refresh()->status)->toBe(MessageStatus::Queued)->and($message->error)->toContain('timed out');

    $sender->deliver($message, final: true);
    expect($message->refresh()->status)->toBe(MessageStatus::Failed);
});

it('does not enqueue for an invalid phone', function (): void {
    Queue::fake();
    smsWorld();

    expect(SendSms::enqueue('manual', '12345', 'Hello'))->toBeNull();
    Queue::assertNothingPushed();
});

it('sends a due reminder to customers with a due and skips the rest', function (): void {
    Queue::fake();
    $world = smsWorld();
    $owes = $world->customer(['name' => 'Karim', 'phone' => '01812345678']);
    $world->sell([$world->line(shirt($world), 1)], [], $owes);
    $clear = $world->customer();
    $owes->refresh();

    $result = app(SendDueReminder::class)->handle($world->user(), [$owes, $clear, $world->walkIn]);

    expect($result)->toBe(['queued' => 1, 'skipped' => 2]);
    expect(Message::query()->where('event', 'due_reminder')->sole()->body)->toContain('Karim')->toContain('1,500.00');
});

it('refuses a due reminder without a provider, and without settings.manage', function (): void {
    $world = smsWorld(['driver' => 'null']);
    expect(fn () => app(SendDueReminder::class)->handle($world->user(), []))->toThrow(SmsNotConfigured::class);
    expect(fn () => app(SendDueReminder::class)->handle($world->user('Cashier'), []))->toThrow(AuthorizationException::class);
});

it('queues a statement SMS and gates the hook', function (): void {
    Queue::fake();
    $world = smsWorld();
    $customer = $world->customer(['name' => 'Rahim', 'phone' => '01712345678']);

    $message = app(SendCustomerStatement::class)->handle($world->user(), $customer, 'Opening 0, billed 1,500, paid 0, due 1,500');

    expect($message?->event)->toBe('statement')->and($message?->body)->toContain('Rahim')->toContain('due 1,500');
    expect(fn () => app(SendCustomerStatement::class)->handle($world->user('Cashier'), $customer, 'x'))->toThrow(AuthorizationException::class);
});

it('saves settings with an encrypted token, keeps it on a blank save, and clears it on request', function (): void {
    $world = smsWorld();
    $save = fn (array $over = []) => app(SaveSmsSettings::class)->handle($world->user(), [
        'driver' => 'ssl_wireless', 'sender_id' => 'SHOP', 'sid' => 'SID', 'base_url' => '', 'templates' => [
            'sale_completed' => ['enabled' => true, 'body' => 'Hi {customer}'],
            'payment_received' => ['enabled' => false, 'body' => ''],
            'due_reminder' => ['enabled' => true, 'body' => 'Pay {due}'],
        ], ...$over,
    ]);

    $stored = $save(['api_token' => 'SECRET-TOKEN'])->refresh()->settings['sms'];
    expect($stored['api_token'])->not->toContain('SECRET')->and(Crypt::decryptString($stored['api_token']))->toBe('SECRET-TOKEN')
        ->and($stored['templates']['payment_received'])->toEqual(['enabled' => false, 'body' => Templates::DEFAULTS['payment_received']]);

    $kept = $save(['api_token' => ''])->refresh()->settings['sms']['api_token'];
    expect($kept)->toBe($stored['api_token']);

    expect($save(['clear_token' => true])->refresh()->settings['sms']['api_token'])->toBeNull();
    expect(json_encode(Activity::query()->get()->map(fn (Activity $a) => [$a->properties, $a->attribute_changes])->all()))->not->toContain('SECRET');
    expect(fn () => app(SaveSmsSettings::class)->handle($world->user('Cashier'), ['driver' => 'log']))->toThrow(AuthorizationException::class);
});

it('sends a test SMS through the configured driver and logs it', function (): void {
    $world = smsWorld(['driver' => 'log']);

    $message = app(SendTestSms::class)->handle($world->user(), '০১৭১২৩৪৫৬৭৮');

    expect($message->status)->toBe(MessageStatus::Sent)->and($message->phone)->toBe('+8801712345678')->and($message->event)->toBe('test');
    $none = smsWorld(['driver' => 'null']);
    expect(app(SendTestSms::class)->handle($none->user(), '01712345678')->status)->toBe(MessageStatus::Failed);
});

it('resends only failed messages', function (): void {
    Queue::fake();
    $world = smsWorld();
    $failed = Message::factory()->create(['tenant_id' => $world->tenant->id, 'status' => MessageStatus::Failed, 'error' => 'x']);
    $sent = Message::factory()->create(['tenant_id' => $world->tenant->id, 'status' => MessageStatus::Sent]);

    app(ResendMessage::class)->handle($world->user(), $failed);

    expect($failed->refresh()->status)->toBe(MessageStatus::Queued)->and($failed->error)->toBeNull();
    Queue::assertPushed(SendSms::class);
    expect(fn () => app(ResendMessage::class)->handle($world->user(), $sent))->toThrow(DomainException::class);
});

it('tenant B never sees tenant A messages', function (): void {
    $a = smsWorld();
    Message::factory()->create(['tenant_id' => $a->tenant->id]);
    $b = SalesWorld::make();

    expect(Message::query()->count())->toBe(0)
        ->and(Tenant::query()->count())->toBeGreaterThan(1);
});
