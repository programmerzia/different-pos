<?php

declare(strict_types=1);

use App\Actions\Purchasing\ReceivePurchase;
use App\Models\Payment;
use App\Models\Vendor;
use App\Support\Money\Money;
use App\Support\Permissions\SystemRoles;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

it('requires vendors.view', function (): void {
    $this->get('/app/vendors')->assertRedirect();

    $world = CatalogWorld::make(role: null);
    $this->actingAs($world->user)->get('/app/vendors')->assertForbidden();
});

it('lists, searches and filters vendors of this tenant only', function (): void {
    $world = CatalogWorld::make();
    $other = CatalogWorld::make();
    app(TenantContext::class)->withoutTenancy(function () use ($world, $other): void {
        Vendor::factory()->create(['tenant_id' => $world->tenant->id, 'name' => 'Alpha Textiles', 'balance' => Money::ofPaisa(5000)]);
        Vendor::factory()->create(['tenant_id' => $world->tenant->id, 'name' => 'Beta Buttons']);
        Vendor::factory()->create(['tenant_id' => $other->tenant->id, 'name' => 'Alpha Foreign']);
    });

    $this->actingAs($world->user)->get('/app/vendors?q=alpha')
        ->assertInertia(fn (Assert $page) => $page->component('vendors/index')->has('rows', 1)->where('rows.0.name', 'Alpha Textiles')->where('rows.0.balance', 5000));
    $this->actingAs($world->user)->get('/app/vendors?filter[status]=owing')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1));
});

it('creates, shows and updates a vendor; validates the phone through the action', function (): void {
    $world = CatalogWorld::make();

    $this->actingAs($world->user)->post('/app/vendors', ['name' => 'Gamma', 'phone' => 'not-a-phone'])->assertSessionHasErrors('form');
    $this->actingAs($world->user)->post('/app/vendors', ['name' => ''])->assertSessionHasErrors('name');

    $this->actingAs($world->user)->post('/app/vendors', ['name' => 'Gamma', 'company' => 'Gamma Ltd', 'phone' => '01712345678'])->assertRedirect();
    $vendor = app(TenantContext::class)->withoutTenancy(fn () => Vendor::query()->where('name', 'Gamma')->firstOrFail());

    $this->actingAs($world->user)->get("/app/vendors/{$vendor->uuid}")
        ->assertInertia(fn (Assert $page) => $page->component('vendors/show')->where('vendor.name', 'Gamma')->where('vendor.balance', 0)->where('canPay', true));
    $this->actingAs($world->user)->put("/app/vendors/{$vendor->uuid}", ['name' => 'Gamma Two'])->assertRedirect();
    expect(app(TenantContext::class)->withoutTenancy(fn () => Vendor::query()->findOrFail($vendor->id)->name))->toBe('Gamma Two');
});

it('404s another tenant\'s vendor on every route', function (): void {
    $mine = CatalogWorld::make();
    $theirs = CatalogWorld::make()->vendor();

    $this->actingAs($mine->user)->get("/app/vendors/{$theirs->uuid}")->assertNotFound();
    $this->actingAs($mine->user)->get("/app/vendors/{$theirs->uuid}/edit")->assertNotFound();
    $this->actingAs($mine->user)->put("/app/vendors/{$theirs->uuid}", ['name' => 'x'])->assertNotFound();
    $this->actingAs($mine->user)->delete("/app/vendors/{$theirs->uuid}")->assertNotFound();
    $this->actingAs($mine->user)->post("/app/vendors/{$theirs->uuid}/payments", ['amount' => 1])->assertNotFound();
});

it('lets a stock keeper manage vendors but not edit as a cashier', function (): void {
    $cashier = CatalogWorld::make(SystemRoles::CASHIER);
    $vendor = $cashier->vendor();
    $this->actingAs($cashier->user)->get('/app/vendors')->assertForbidden();
    $this->actingAs($cashier->user)->get("/app/vendors/{$vendor->uuid}")->assertForbidden();

    $keeper = CatalogWorld::make(SystemRoles::STOCK_KEEPER);
    $this->actingAs($keeper->user)->get('/app/vendors/create')->assertOk();
});

it('deactivates a vendor', function (): void {
    $world = CatalogWorld::make();
    $vendor = $world->vendor();

    $this->actingAs($world->user)->delete("/app/vendors/{$vendor->uuid}")->assertRedirect('/app/vendors');

    expect(app(TenantContext::class)->withoutTenancy(fn () => Vendor::query()->findOrFail($vendor->id)->is_active))->toBeFalse();
});

it('pays a vendor from the profile, oldest dues first, and refuses without payments.pay', function (): void {
    $world = CatalogWorld::make();
    $world->enter();
    $vendor = $world->vendor();
    $method = $world->paymentMethod();
    $product = $world->product();
    app(ReceivePurchase::class)->handle($world->user, [
        'vendor_id' => $vendor->id,
        'items' => [['product_id' => $product->id, 'qty' => Quantity::ofUnits(2), 'unit_cost' => Money::ofPaisa(10000)]],
    ]);

    $this->actingAs($world->user)->post("/app/vendors/{$vendor->uuid}/payments", ['amount' => 5000, 'payment_method_id' => $method->id])->assertRedirect();
    expect(Vendor::query()->findOrFail($vendor->id)->balance->paisa)->toBe(15000)
        ->and(Payment::query()->count())->toBe(1);

    $this->actingAs($world->user)->post("/app/vendors/{$vendor->uuid}/payments", ['amount' => 0, 'payment_method_id' => $method->id])
        ->assertSessionHasErrors('amount');

    $keeper = CatalogWorld::make(SystemRoles::ACCOUNTANT);
    $keeperVendor = $keeper->vendor();
    $this->actingAs($keeper->user)->post("/app/vendors/{$keeperVendor->uuid}/payments", ['amount' => 100, 'payment_method_id' => $keeper->paymentMethod()->id])->assertRedirect();
    $cashier = CatalogWorld::make(SystemRoles::CASHIER);
    $this->actingAs($cashier->user)->post("/app/vendors/{$cashier->vendor()->uuid}/payments", ['amount' => 100])->assertForbidden();
});
