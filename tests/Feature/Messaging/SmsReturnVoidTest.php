<?php

declare(strict_types=1);

use App\Actions\Sales\CreateExchange;
use App\Actions\Sales\CreateSaleReturn;
use App\Actions\Sales\Data\ExchangeDraft;
use App\Actions\Sales\Data\ReturnDraft;
use App\Actions\Sales\Data\ReturnLineDraft;
use App\Actions\Sales\Data\SaleDraft;
use App\Actions\Sales\VoidSale;
use App\Enums\RefundMode;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Sale;
use App\Sms\SendSms;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    $this->travelTo(now()->setTimezone('Asia/Dhaka')->setDate(2026, 11, 3)->setTime(14, 0)->utc());
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

function rvWorld(array $sms = ['driver' => 'log']): SalesWorld
{
    $world = SalesWorld::make(['sms' => $sms]);
    $GLOBALS['rv_shirt'][$world->tenant->id] = $world->product(150_000, 60_000);
    $world->stock($GLOBALS['rv_shirt'][$world->tenant->id], 20, 60_000);

    return $world;
}

function rvSell(SalesWorld $world, ?Customer $customer, int $cash = 150_000): Sale
{
    return $world->sell([$world->line($GLOBALS['rv_shirt'][$world->tenant->id], 1)], [$world->tender('cash', $cash)], $customer);
}

function rvReturn(SalesWorld $world, Sale $sale, RefundMode $mode = RefundMode::Refund): void
{
    app(CreateSaleReturn::class)->handle($world->user(), new ReturnDraft($sale->id, [new ReturnLineDraft($sale->items[0]->id, Quantity::ofUnits(1))], $mode, 'size'));
}

it('queues a return SMS with the amount handed back and the remaining due', function (): void {
    Queue::fake();
    $world = rvWorld();
    $customer = $world->customer(['name' => 'Rahim', 'phone' => '01712345678']);
    $sale = rvSell($world, $customer, 100_000);
    Message::query()->delete();
    Queue::fake();

    rvReturn($world, $sale);

    $message = Message::query()->sole();
    expect($message->event)->toBe('sale_returned')
        ->and($message->customer_id)->toBe($customer->id)
        ->and($message->body)->toBe('Dear Rahim, your return on invoice '.$sale->number.' is recorded: ৳1,500.00. Remaining due ৳0.00. — '.$world->tenant->name);
    Queue::assertPushed(SendSms::class, 1);
});

it('queues a void SMS with the invoice total', function (): void {
    Queue::fake();
    $world = rvWorld();
    $customer = $world->customer(['name' => 'Karim', 'phone' => '01812345678']);
    $sale = rvSell($world, $customer);
    Message::query()->delete();

    app(VoidSale::class)->handle($world->user(), $sale, 'wrong item');

    $message = Message::query()->sole();
    expect($message->event)->toBe('sale_voided')
        ->and($message->body)->toBe('Dear Karim, invoice '.$sale->number.' (৳1,500.00) has been cancelled. — '.$world->tenant->name);
});

it('sends nothing when the template is off, the driver is null, or the customer is a walk-in or has no phone', function (): void {
    Queue::fake();
    $cases = [
        fn (): SalesWorld => rvWorld(['driver' => 'log', 'templates' => ['sale_returned' => ['enabled' => false, 'body' => 'x'], 'sale_voided' => ['enabled' => false, 'body' => 'x']]]),
        fn (): SalesWorld => rvWorld(['driver' => 'null']),
        fn (): SalesWorld => rvWorld(),
        fn (): SalesWorld => rvWorld(),
    ];
    foreach ($cases as $index => $make) {
        $world = $make();
        $customer = match ($index) {
            2 => null,
            3 => $world->customer(['phone' => null]),
            default => $world->customer(['phone' => '01712345678']),
        };
        rvReturn($world, rvSell($world, $customer));
        app(VoidSale::class)->handle($world->user(), rvSell($world, $customer), 'oops');
    }

    expect(Message::query()->whereIn('event', ['sale_returned', 'sale_voided'])->count())->toBe(0);
});

it('an exchange sends no SMS at all (no return SMS, and the replacement sale does not fire SaleCompleted)', function (): void {
    Queue::fake();
    $world = rvWorld();
    $shirt = $GLOBALS['rv_shirt'][$world->tenant->id];
    $customer = $world->customer(['phone' => '01712345678']);
    $sale = rvSell($world, $customer);
    Message::query()->delete();

    app(CreateExchange::class)->handle($world->user(), new ExchangeDraft(
        $sale->id,
        [new ReturnLineDraft($sale->items[0]->id, Quantity::ofUnits(1))],
        new SaleDraft([$world->line($shirt)], [], $customer->id),
        RefundMode::Refund,
    ));

    expect(Message::query()->where('event', 'sale_returned')->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0);
});

it('a failing queue does not break the return or the void', function (): void {
    $world = rvWorld();
    $customer = $world->customer(['phone' => '01712345678']);
    $sale = rvSell($world, $customer);
    $other = rvSell($world, $customer);
    Message::creating(static fn () => throw new RuntimeException('boom'));

    rvReturn($world, $sale);
    $voided = app(VoidSale::class)->handle($world->user(), $other, 'oops');

    expect($sale->refresh()->returned_total->paisa)->toBe(150_000)
        ->and($voided->status->value)->toBe('voided')
        ->and(Message::query()->whereIn('event', ['sale_returned', 'sale_voided'])->count())->toBe(0);
});

it('tenant A with SMS off is unaffected by tenant B returns', function (): void {
    Queue::fake();
    $a = rvWorld(['driver' => 'null']);
    $customerA = $a->customer(['phone' => '01712345678']);
    $saleA = rvSell($a, $customerA);
    $b = rvWorld();
    $customerB = $b->customer(['phone' => '01712345678']);
    rvReturn($b, rvSell($b, $customerB));

    $messages = Message::query()->whereIn('event', ['sale_returned', 'sale_voided'])->get();
    expect($messages)->toHaveCount(1)
        ->and($messages->sole()->tenant_id)->toBe($b->tenant->id)
        ->and($saleA->tenant_id)->toBe($a->tenant->id);
});
