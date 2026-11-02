<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Message;
use App\Sms\SendSms;
use App\Support\Customers\CustomerStatement;
use App\Support\Money\Money;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CustomerWorld;

it('totals completed sales inside the period only', function (): void {
    [$user, $tenant, $branch] = CustomerWorld::actor();
    $customer = CustomerWorld::make($tenant, ['name' => 'Wholesale Buyer', 'customer_no' => 10000017]);
    CustomerWorld::sale($tenant, $branch, $customer, ['number' => 'S-1', 'sold_at' => '2026-10-05 10:00:00', 'total' => Money::ofPaisa(100000), 'paid' => Money::ofPaisa(60000), 'due' => Money::ofPaisa(40000), 'payment_status' => PaymentStatus::Partial]);
    CustomerWorld::sale($tenant, $branch, $customer, ['number' => 'S-2', 'sold_at' => '2026-10-31 23:30:00', 'total' => Money::ofPaisa(25050), 'paid' => Money::ofPaisa(25050), 'due' => Money::zero(), 'payment_status' => PaymentStatus::Paid]);
    CustomerWorld::sale($tenant, $branch, $customer, ['number' => 'S-OUT', 'sold_at' => '2026-11-01 00:10:00', 'total' => Money::ofPaisa(999)]);
    CustomerWorld::sale($tenant, $branch, $customer, ['number' => 'S-VOID', 'sold_at' => '2026-10-06 10:00:00', 'status' => SaleStatus::Voided, 'total' => Money::ofPaisa(777)]);

    $this->actingAs($user)->get("/app/customers/{$customer->uuid}/statement?from=2026-10-01&to=2026-10-31")->assertOk()->assertInertia(
        fn (Assert $p) => $p->component('customers/statement')->where('customer.name', 'Wholesale Buyer')->has('rows', 2)
            ->where('rows.0.number', 'S-1')->where('rows.1.number', 'S-2')
            ->where('totals.count', 2)->where('totals.total', 125050)->where('totals.paid', 85050)->where('totals.due', 40000)
            ->where('period.from', '2026-10-01')->where('period.to', '2026-10-31'),
    );
});

it('builds the same totals from the support class and can widen to all branches', function (): void {
    [, $tenant, $branch] = CustomerWorld::actor();
    $otherBranch = makeBranch($tenant);
    $customer = CustomerWorld::make($tenant, ['customer_no' => 10000017]);
    CustomerWorld::sale($tenant, $branch, $customer, ['sold_at' => '2026-10-05 10:00:00', 'total' => Money::ofPaisa(100)]);
    CustomerWorld::sale($tenant, $otherBranch, $customer, ['sold_at' => '2026-10-06 10:00:00', 'total' => Money::ofPaisa(200)]);
    app(TenantContext::class)->set($tenant, $branch);

    $from = CarbonImmutable::parse('2026-10-01');
    $to = CarbonImmutable::parse('2026-10-31');
    $statement = new CustomerStatement;
    expect($statement->build($customer, $from, $to)['totals']['total'])->toBe(100)
        ->and($statement->build($customer, $from, $to, true)['totals']['total'])->toBe(300);
    app(TenantContext::class)->forget();
});

it('defaults to this month, validates the period and renders the empty picker page', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    $customer = CustomerWorld::make($tenant, ['customer_no' => 10000017]);

    $this->actingAs($user);
    $this->get('/app/customers/statement')->assertOk()->assertInertia(
        fn (Assert $p) => $p->component('customers/statement')->where('customer', null)->where('totals', null)
            ->where('period.from', CarbonImmutable::today()->startOfMonth()->toDateString()),
    );
    $this->get("/app/customers/{$customer->uuid}/statement?from=2026-10-31&to=2026-10-01")->assertSessionHasErrors('to');
});

it('queues the statement SMS through the SMS module and needs permission', function (): void {
    Queue::fake();
    [$user, $tenant] = CustomerWorld::actor();
    $customer = CustomerWorld::make($tenant, ['customer_no' => 10000017, 'phone' => '+8801711111111']);
    $noPhone = CustomerWorld::make($tenant, ['customer_no' => 10000025, 'phone' => null]);

    $this->actingAs($user)->post("/app/customers/{$customer->uuid}/statement/sms", ['from' => '2026-10-01', 'to' => '2026-10-31'])->assertRedirect();
    expect(Message::query()->where('customer_id', $customer->id)->count())->toBe(1);
    Queue::assertPushed(SendSms::class);

    $this->post("/app/customers/{$noPhone->uuid}/statement/sms")->assertRedirect();
    expect(Message::query()->where('customer_id', $noPhone->id)->count())->toBe(0);

    [$keeper, $keeperTenant] = CustomerWorld::actor(SystemRoles::STOCK_KEEPER);
    $mine = CustomerWorld::make($keeperTenant, ['customer_no' => 10000033]);
    $this->actingAs($keeper)->post("/app/customers/{$mine->uuid}/statement/sms")->assertForbidden();
    $this->get("/app/customers/{$mine->uuid}/statement")->assertForbidden();
});
