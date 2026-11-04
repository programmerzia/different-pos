<?php

declare(strict_types=1);

use App\Actions\Messaging\SendDueReminder;
use App\Enums\SaleStatus;
use App\Exceptions\Domain\SmsNotConfigured;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Product;
use App\Models\Sale;
use App\Sms\MessageContext;
use App\Sms\SendSms;
use App\Sms\SmsNotice;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\SalesWorld;

const NOTICE_EN = 'SMS not sent: the SMS gateway is not set up yet (Settings > SMS).';

beforeEach(function (): void {
    $this->travelTo(now()->setTimezone('Asia/Dhaka')->setDate(2026, 11, 3)->setTime(14, 0)->utc());
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

/** @param  array<string, mixed>  $sms */
function noticeWorld(array $sms): SalesWorld
{
    $world = SalesWorld::make(['sms' => $sms]);
    foreach ($world->users as $user) {
        $user->branches()->attach($world->branch);
    }
    $product = $world->product(150_000, 60_000);
    $world->stock($product, 20, 60_000);
    $GLOBALS['notice_product'][$world->tenant->id] = $product;

    return $world;
}

function noticeProduct(SalesWorld $world): Product
{
    return $GLOBALS['notice_product'][$world->tenant->id];
}

function noticeCustomer(SalesWorld $world, array $attributes = []): Customer
{
    return $world->customer(['name' => 'Rahim', 'phone' => '01712345678', ...$attributes]);
}

/** @return array<string, mixed> */
function noticePayload(SalesWorld $world, ?Customer $customer, array $overrides = []): array
{
    return [
        'client_uuid' => (string) Str::uuid(),
        'customer_id' => $customer?->id,
        'lines' => [['product_id' => noticeProduct($world)->id, 'qty' => 1000, 'discount' => ['type' => 'none', 'value' => 0]]],
        'order_discount' => ['type' => 'none', 'value' => 0],
        'tenders' => [['payment_method_id' => $world->methods['cash']->id, 'amount' => 100_000]],
        'expected_total' => 150_000,
        ...$overrides,
    ];
}

const NO_KEY = ['driver' => 'ssl_wireless', 'sid' => 'SHOP'];

it('treats each driver as configured only with its credentials', function (): void {
    $token = Crypt::encryptString('T');
    $cases = [
        [['driver' => 'null'], false],
        [['driver' => 'log'], true],
        [['driver' => 'ssl_wireless', 'sid' => 'S'], false],
        [['driver' => 'ssl_wireless', 'api_token' => $token], false],
        [['driver' => 'ssl_wireless', 'sid' => 'S', 'api_token' => $token], true],
        [['driver' => 'ssl_wireless', 'sender_id' => 'SHOP', 'api_token' => $token], true],
        [['driver' => 'onecodesoft', 'api_token' => $token], false],
        [['driver' => 'onecodesoft', 'base_url' => 'https://a.example.test'], false],
        [['driver' => 'onecodesoft', 'base_url' => 'https://a.example.test', 'api_token' => $token], true],
        [['driver' => 'ssl_wireless', 'sid' => 'S', 'api_token' => 'not-encrypted-garbage'], false],
    ];
    foreach ($cases as $i => [$sms, $expected]) {
        $world = SalesWorld::make(['sms' => $sms]);
        expect(SmsSettings::fromTenant($world->tenant)->isConfigured())->toBe($expected, 'case '.$i);
        app(TenantContext::class)->forget();
    }
});

it('does not queue or fail the sale for a real driver with no key, and the POS answer carries the notice', function (): void {
    Queue::fake();
    $world = noticeWorld(NO_KEY);
    $customer = noticeCustomer($world);

    $response = $this->actingAs($world->user('Cashier'))->postJson('/app/pos/sales', noticePayload($world, $customer))
        ->assertCreated()
        ->assertJsonPath('sale.sms_notice', NOTICE_EN);

    expect(Sale::query()->sole()->status)->toBe(SaleStatus::Completed)
        ->and(Message::query()->count())->toBe(0);
    Queue::assertNothingPushed();
    expect($response->json('sale.id'))->toBeInt();
});

it('gives a Bangla notice to a Bangla session', function (): void {
    $world = noticeWorld(NO_KEY);
    app()->setLocale('bn');

    expect(SmsNotice::missing(Templates::SALE_COMPLETED, noticeCustomer($world)))
        ->toBe('এসএমএস পাঠানো হয়নি: এসএমএস গেটওয়ে এখনো সেট করা নেই (সেটিংস > এসএমএস)।');
    app()->setLocale('en');
});

it('queues the sale SMS and shows no notice when the key is present', function (): void {
    Queue::fake();
    $world = noticeWorld([...NO_KEY, 'api_token' => Crypt::encryptString('T')]);
    $customer = noticeCustomer($world);

    $this->actingAs($world->user('Cashier'))->postJson('/app/pos/sales', noticePayload($world, $customer))
        ->assertCreated()
        ->assertJsonPath('sale.sms_notice', null);

    Queue::assertPushed(SendSms::class, 1);
    expect(Message::query()->count())->toBe(1);
});

it('shows the notice for a named customer with the null driver, not for a walk-in or an unreachable one', function (): void {
    $world = noticeWorld(['driver' => 'null']);
    $cashier = $world->user('Cashier');

    $this->actingAs($cashier)->postJson('/app/pos/sales', noticePayload($world, noticeCustomer($world)))
        ->assertCreated()->assertJsonPath('sale.sms_notice', NOTICE_EN);
    $this->actingAs($cashier)->postJson('/app/pos/sales', noticePayload($world, null, ['tenders' => [['payment_method_id' => $world->methods['cash']->id, 'amount' => 150_000]]]))
        ->assertCreated()->assertJsonPath('sale.sms_notice', null);
    $noPhone = noticeCustomer($world, ['name' => 'No Phone', 'phone' => null]);
    $this->actingAs($cashier)->postJson('/app/pos/sales', noticePayload($world, $noPhone))
        ->assertCreated()->assertJsonPath('sale.sms_notice', null);
});

it('shows no notice when the template is switched off', function (): void {
    $world = noticeWorld([...NO_KEY, 'templates' => [Templates::SALE_COMPLETED => ['enabled' => false]]]);

    $this->actingAs($world->user('Cashier'))->postJson('/app/pos/sales', noticePayload($world, noticeCustomer($world)))
        ->assertCreated()->assertJsonPath('sale.sms_notice', null);
});

it('sends no notice for a replayed sale', function (): void {
    $world = noticeWorld(NO_KEY);
    $payload = noticePayload($world, noticeCustomer($world));
    $cashier = $world->user('Cashier');

    $this->actingAs($cashier)->postJson('/app/pos/sales', $payload)->assertCreated()->assertJsonPath('sale.sms_notice', NOTICE_EN);
    $this->actingAs($cashier)->postJson('/app/pos/sales', $payload)->assertOk()->assertJsonPath('sale.sms_notice', null);
    expect(Sale::query()->count())->toBe(1);
});

it('treats onecodesoft with a key but no base URL as not configured', function (): void {
    Queue::fake();
    $world = noticeWorld(['driver' => 'onecodesoft', 'api_token' => Crypt::encryptString('T')]);

    $this->actingAs($world->user('Cashier'))->postJson('/app/pos/sales', noticePayload($world, noticeCustomer($world)))
        ->assertCreated()->assertJsonPath('sale.sms_notice', NOTICE_EN);
    Queue::assertNothingPushed();
});

it('never throws: a failing helper returns null and is reported', function (): void {
    Exceptions::fake();
    $world = noticeWorld(NO_KEY);
    $customer = noticeCustomer($world);
    app()->bind(MessageContext::class, static fn () => throw new RuntimeException('boom'));

    expect(SmsNotice::missing(Templates::SALE_COMPLETED, $customer))->toBeNull();
    Exceptions::assertReported(RuntimeException::class);
});

it('flashes the info notice beside the success toast for a void, a payment and a return', function (): void {
    Queue::fake();
    $world = noticeWorld(NO_KEY);
    $customer = noticeCustomer($world);
    $manager = $world->user('Manager');
    $sale = $world->sell([$world->line(noticeProduct($world), 1)], [$world->tender('cash', 50_000)], $customer);
    $item = $sale->items()->first();

    $this->actingAs($manager)->post(route('dues.payments.store', $customer->uuid), [
        'amount' => 10_000, 'payment_method_id' => $world->methods['cash']->id,
    ])->assertRedirect()->assertInertiaFlash('toast.type', 'success')->assertInertiaFlash('toast_info', NOTICE_EN);

    $this->actingAs($manager)->post(route('sales.payments.store', $sale->id), [
        'amount' => 10_000, 'payment_method_id' => $world->methods['cash']->id,
    ])->assertRedirect()->assertInertiaFlash('toast.type', 'success')->assertInertiaFlash('toast_info', NOTICE_EN);

    $this->actingAs($manager)->post(route('returns.store'), [
        'sale_id' => $sale->id,
        'lines' => [['sale_item_id' => $item->id, 'qty' => 1000, 'restock' => true]],
        'refund_mode' => 'refund',
        'reason' => 'size',
    ])->assertRedirect()->assertInertiaFlash('toast_info', NOTICE_EN);

    $second = $world->sell([$world->line(noticeProduct($world), 1)], [$world->tender('cash', 150_000)], $customer);
    $this->actingAs($manager)->post(route('sales.void', $second->id), ['reason' => 'wrong'])
        ->assertRedirect()->assertInertiaFlash('toast.type', 'success')->assertInertiaFlash('toast_info', NOTICE_EN);

    expect($second->refresh()->status)->toBe(SaleStatus::Voided)->and(Message::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('flashes no notice for these actions when the key is present', function (): void {
    Queue::fake();
    $world = noticeWorld([...NO_KEY, 'api_token' => Crypt::encryptString('T')]);
    $customer = noticeCustomer($world);
    $sale = $world->sell([$world->line(noticeProduct($world), 1)], [$world->tender('cash', 150_000)], $customer);

    $this->actingAs($world->user('Manager'))->post(route('sales.void', $sale->id), ['reason' => 'wrong'])
        ->assertRedirect()->assertInertiaFlash('toast.type', 'success')->assertInertiaFlashMissing('toast_info');
    Queue::assertPushed(SendSms::class);
});

it('shows the settings page a configured flag and never the key', function (): void {
    $world = noticeWorld(NO_KEY);
    $owner = $world->user('Owner');

    $this->actingAs($owner)->get(route('settings.sms.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('sms.configured', false)->where('sms.has_token', false));
});

it('blocks a manual send with the clear not-configured message when a real driver has no key', function (): void {
    Queue::fake();
    $world = noticeWorld(NO_KEY);

    expect(fn () => app(SendDueReminder::class)->handle($world->user('Owner'), [noticeCustomer($world)]))->toThrow(SmsNotConfigured::class);
    expect(Message::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});
