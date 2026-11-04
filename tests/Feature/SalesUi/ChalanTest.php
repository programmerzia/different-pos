<?php

declare(strict_types=1);

use App\Models\Sale;
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
    // Part-paid on purpose: the sale carries a due that must never reach the chalan.
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
        ->assertSee('Vehicle no.')
        ->assertSee('Driver')
        ->assertSee('Prepared by')
        ->assertSee('Authorised by')
        ->assertSee('Received by')
        ->assertHeader('X-Robots-Tag', 'noindex');
});

it('prints in Bangla', function (): void {
    $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', [$this->sale->id, 'lang' => 'bn']))
        ->assertOk()
        ->assertSee('<html lang="bn">', false)
        ->assertSee('ডেলিভারি চালান')
        ->assertSee('গ্রহণকারী');
});

it('shows no money anywhere', function (): void {
    $html = $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', $this->sale->id))->assertOk()->getContent();
    $bn = $this->actingAs($this->world->user('Cashier'))->get(route('sales.chalan', [$this->sale->id, 'lang' => 'bn']))->assertOk()->getContent();

    foreach ([$html, $bn] as $page) {
        expect($page)
            ->not->toContain('1,234.50')
            ->not->toContain('2,469.00')
            ->not->toContain('2,000.00')
            ->not->toContain('469.00')
            ->not->toContain('777.00')
            ->not->toContain('৳')
            ->not->toContain('১,২৩৪')
            ->not->toContain('২,৪৬৯')
            ->not->toContain('Price')
            ->not->toContain('Subtotal')
            ->not->toContain('Discount')
            ->not->toContain('VAT')
            ->not->toContain('Paid')
            ->not->toContain('Due')
            ->not->toContain('Amount')
            ->not->toContain('Payments');
    }
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
