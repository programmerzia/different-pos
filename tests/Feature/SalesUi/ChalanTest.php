<?php

declare(strict_types=1);

use App\Models\Courier;
use App\Models\Sale;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Tests\Fixtures\SalesUi;

beforeEach(function (): void {
    $this->world = SalesUi::world();
    $this->world->tenant->update(['name' => 'Different Fashion']);
    $this->shirt = $this->world->product(123_450, 77_700, ['name' => 'Silk Panjabi', 'sku' => 'SP-01']);
    $this->world->stock($this->shirt, 10, 77_700);
    $this->customer = $this->world->customer([
        'name' => 'Rahim', 'shop_name' => 'Rahim Store', 'phone' => '+8801711000111', 'address_line' => 'House 9, Road 4',
    ]);
    // Part-paid on purpose: the sale owes ৳469.00, which prints as the COD amount and nothing else about money.
    $this->sale = SalesUi::sale($this->world, $this->shirt, $this->customer, 2, 200_000);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('prints the chalan with number, items, quantities and the hand-fill lines', function (): void {
    $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', $this->sale->id))
        ->assertOk()
        ->assertSee('size: A4', false)
        ->assertSee('Delivery Chalan')
        ->assertSee('CH-'.$this->sale->number)
        ->assertSee('Invoice no.')
        ->assertSee('Rahim')
        ->assertSee('Rahim Store')
        ->assertSee('+8801711000111')
        ->assertSee('House 9, Road 4')
        ->assertSee('Silk Panjabi')
        ->assertSee('SP-01')
        ->assertSee('Total quantity')
        ->assertSee('To (receiver)')
        ->assertSee('From (sender)')
        ->assertDontSee('Tracking no.')
        ->assertDontSee('Booking date')
        ->assertDontSee('Weight')
        ->assertSee('Parcels')
        ->assertSee('COD amount')
        ->assertSee('Packing list')
        ->assertSee('Packed by')
        ->assertSee('Received by courier')
        ->assertSee('Received by customer')
        ->assertSee('<svg', false)
        ->assertHeader('X-Robots-Tag', 'noindex');
});

it('prints in Bangla', function (): void {
    $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', [$this->sale->id, 'lang' => 'bn']))
        ->assertOk()
        ->assertSee('<html lang="bn">', false)
        ->assertSee('ডেলিভারি চালান')
        ->assertSee('গ্রহণকারী');
});

it('shows no money anywhere except the COD amount', function (): void {
    $user = $this->world->user('Cashier');
    $pages = [
        $this->actingAs($user)->get(route('sales.chalan', $this->sale->id))->assertOk()->getContent(),
        $this->actingAs($user)->get(route('sales.chalan', [$this->sale->id, 'lang' => 'bn']))->assertOk()->getContent(),
        $this->actingAs($user)->get(route('sales.chalan', [$this->sale->id, 'cod' => 0]))->assertOk()->getContent(),
    ];

    foreach ($pages as $page) {
        expect($page)
            ->not->toContain('1,234.50')
            ->not->toContain('2,469.00')
            ->not->toContain('2,000.00')
            ->not->toContain('777.00')
            ->not->toContain('১,২৩৪')
            ->not->toContain('২,৪৬৯')
            ->not->toContain('Price')
            ->not->toContain('Subtotal')
            ->not->toContain('Discount')
            ->not->toContain('VAT')
            ->not->toContain('Paid')
            ->not->toContain('Due')
            ->not->toContain('Payments');
    }
    expect($pages[2])->not->toContain('469.00')->not->toContain('৳')->not->toContain('COD amount');
});

it('prints the due as the COD amount in English and Bangla when the sale owes something', function (): void {
    $user = $this->world->user('Cashier');

    $this->actingAs($user)->get(route('sales.chalan', $this->sale->id))->assertOk()
        ->assertSee('COD amount')->assertSee('469.00')->assertSee('COD: on');
    $bn = $this->actingAs($user)->get(route('sales.chalan', [$this->sale->id, 'lang' => 'bn']))->assertOk()->getContent();
    expect($bn)->toContain('সিওডি পরিমাণ')->toContain('৪৬৯.০০')->toContain('সিওডি: চালু')->not->toContain('469.00');
});

it('hides the COD box and its toggle when nothing is due', function (): void {
    Sale::query()->whereKey($this->sale->id)->update(['due' => 0, 'paid' => 246_900]);

    $html = $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', $this->sale->id))->assertOk()->getContent();

    expect($html)->not->toContain('COD')->not->toContain('469.00')->not->toContain('৳');
});

it('hides the COD box with cod=0, offers the toggle back, and keeps cod=0 across the links', function (): void {
    courierFor($this->world, ['name' => 'Sundarban Courier', 'is_default' => true]);
    courierFor($this->world, ['name' => 'RedX']);

    $html = $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', [$this->sale->id, 'cod' => 0]))->assertOk()
        ->assertDontSee('COD amount')->assertDontSee('469.00')->assertSee('COD: off')->getContent();

    expect($html)->toMatch('/href="[^"]*lang=bn[^"]*cod=0/')->toMatch('/href="[^"]*courier=\d+[^"]*cod=0/');
    // The toggle link from the off state goes back on (no cod parameter).
    expect($html)->toMatch('/href="[^"]*sales\/'.$this->sale->id.'\/chalan[^"?]*(\?lang=en)?"[^>]*>COD: off/');
});

it('shows no COD amount on a voided sale', function (): void {
    Sale::query()->whereKey($this->sale->id)->update(['status' => 'voided', 'void_reason' => 'wrong size']);

    $html = $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', $this->sale->id))->assertOk()->getContent();

    expect($html)->toContain('void-stamp')->not->toContain('COD')->not->toContain('469.00');
});

it('marks a voided sale as void', function (): void {
    Sale::query()->whereKey($this->sale->id)->update(['status' => 'voided', 'void_reason' => 'wrong size']);

    $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', $this->sale->id))
        ->assertOk()
        ->assertSee('void-stamp', false)
        ->assertSee('wrong size')
        ->assertSee('Silk Panjabi');
});

it('notes returns but still shows the quantity sold', function (): void {
    $this->sale->items()->update(['qty_returned' => '1.000']);

    $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', $this->sale->id))
        ->assertOk()
        ->assertSee('quantities shown are as sold')
        ->assertSee('2');
});

it('404s another branch and another tenant', function (): void {
    $branch = SalesUi::otherBranch($this->world);
    $other = SalesUi::inBranch($this->world, $branch, fn () => Sale::factory()->create(['tenant_id' => $this->world->tenant->id, 'branch_id' => $branch->id, 'customer_id' => $this->customer->id, 'number' => 'DF2-1']));
    $foreign = SalesUi::foreignSale();
    $user = $this->world->user('Cashier');

    $this->actingAs($user)->get(route('sales.chalan', $other->id))->assertNotFound();
    $this->actingAs($user)->get(route('sales.chalan', $foreign->id))->assertNotFound();
});

it('refuses users without sales.view and guests', function (): void {
    $this->actingAs($this->world->user('Stock Keeper'))->get(route('sales.chalan', $this->sale->id))->assertForbidden();
    auth()->logout();
    $this->get(route('sales.chalan', $this->sale->id))->assertRedirect();
});

function courierFor($world, array $attributes): Courier
{
    return app(TenantContext::class)->withoutTenancy(fn (): Courier => Courier::factory()->create(['tenant_id' => $world->tenant->id] + $attributes));
}

it('leaves the courier box blank with no courier at all', function (): void {
    $html = $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', $this->sale->id))->assertOk()->getContent();

    expect($html)->not->toContain('Courier: ')->and($html)->toContain('Courier');
});

it('prints the default courier with its phone', function (): void {
    courierFor($this->world, ['name' => 'Sundarban Courier', 'phone' => '01811223344', 'is_default' => true]);

    $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', $this->sale->id))
        ->assertOk()->assertSee('Sundarban Courier')->assertSee('01811223344');
});

it('prefers the sale courier over the default and never prints its tracking number', function (): void {
    courierFor($this->world, ['name' => 'Sundarban Courier', 'is_default' => true]);
    courierFor($this->world, ['name' => 'Pathao', 'phone' => '01955667788']);
    Sale::query()->whereKey($this->sale->id)->update(['courier_name' => 'pathao', 'courier_tracking' => 'TRK-998877']);

    $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', $this->sale->id))
        ->assertOk()->assertSee('<div class="strong">pathao</div>', false)->assertSee('01955667788')->assertDontSee('TRK-998877')
        ->assertDontSee('<div class="strong">Sundarban Courier</div>', false);
});

it('lets the courier query parameter win and leaves the sale unchanged', function (): void {
    courierFor($this->world, ['name' => 'Sundarban Courier', 'is_default' => true]);
    $redx = courierFor($this->world, ['name' => 'RedX']);
    Sale::query()->whereKey($this->sale->id)->update(['courier_name' => 'Pathao']);

    $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', [$this->sale->id, 'courier' => $redx->id]))
        ->assertOk()->assertSee('<div class="strong">RedX</div>', false)->assertDontSee('<div class="strong">Pathao</div>', false);

    expect(Sale::query()->findOrFail($this->sale->id)->courier_name)->toBe('Pathao');
});

it('ignores a foreign, inactive or invalid courier id', function (): void {
    courierFor($this->world, ['name' => 'Sundarban Courier', 'is_default' => true]);
    $off = courierFor($this->world, ['name' => 'Dormant', 'is_active' => false]);
    $other = Tenant::factory()->create();
    $foreign = app(TenantContext::class)->withoutTenancy(fn (): Courier => Courier::factory()->create(['tenant_id' => $other->id, 'name' => 'Foreign Express']));

    foreach ([$foreign->id, $off->id, 'abc', '99999999', '-1', ''] as $id) {
        $html = $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', [$this->sale->id, 'courier' => $id]))
            ->assertOk()->assertSee('<div class="strong">Sundarban Courier</div>', false)->getContent();
        expect($html)->not->toContain('Foreign Express')->and($html)->not->toContain('Dormant');
    }
});

it('shows a courier chooser in the toolbar only with more than one active courier', function (): void {
    $only = courierFor($this->world, ['name' => 'Sundarban Courier', 'is_default' => true]);
    $user = $this->world->user('Cashier');

    $this->actingAs($user)->get(route('sales.chalan', $this->sale->id))->assertOk()->assertDontSee('Courier: Sundarban Courier');

    $redx = courierFor($this->world, ['name' => 'RedX']);
    courierFor($this->world, ['name' => 'Dormant', 'is_active' => false]);
    $html = $this->actingAs($user)->get(route('sales.chalan', [$this->sale->id, 'print' => 1]))->assertOk()
        ->assertSee('Courier: Sundarban Courier')->assertSee('Courier: RedX')->assertDontSee('Courier: Dormant')
        ->assertSee('courier='.$redx->id.'&amp;print=1', false)->assertSee('lang=bn', false)->getContent();

    // The default is the current one; picking RedX moves the marker and keeps the sale as it was.
    expect($html)->toMatch('/<a href="[^"]*courier='.$only->id.'[^"]*"\s+aria-current="page"/');
    $picked = $this->actingAs($user)->get(route('sales.chalan', [$this->sale->id, 'courier' => $redx->id]))->assertOk()->getContent();
    expect($picked)->toMatch('/<a href="[^"]*courier='.$redx->id.'[^"]*"\s+aria-current="page"/');
});

it('keeps all other money off the chalan when couriers are set', function (): void {
    courierFor($this->world, ['name' => 'Sundarban Courier', 'is_default' => true]);
    $html = $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', $this->sale->id))->assertOk()->getContent();

    expect($html)->not->toContain('2,469.00')->not->toContain('2,000.00');
});
