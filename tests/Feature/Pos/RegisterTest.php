<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VariantOptionValue;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Pos\ManagerApproval;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    $this->world = SalesWorld::make();
    foreach ($this->world->users as $user) {
        $user->branches()->attach($this->world->branch);
    }
    $this->shirt = $this->world->product(150_000, 60_000, ['name' => 'Cotton Shirt', 'sku' => 'SH-1', 'barcode' => '8901001']);
    $this->world->stock($this->shirt, 10, 60_000);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

/** @param  array<string, mixed>  $overrides */
function posRegisterSale(SalesWorld $world, Product $product, array $overrides = []): array
{
    return [
        'client_uuid' => (string) Str::uuid(),
        'lines' => [['product_id' => $product->id, 'qty' => 1000, 'discount' => ['type' => 'none', 'value' => 0]]],
        'order_discount' => ['type' => 'none', 'value' => 0],
        'tenders' => [['payment_method_id' => $world->methods['cash']->id, 'amount' => 200_000]],
        'expected_total' => 150_000,
        ...$overrides,
    ];
}

/** Another tenant with a branch, a product and a customer. */
function posForeignTenant(): array
{
    return app(TenantContext::class)->withoutTenancy(function (): array {
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->for($tenant)->create();

        return [
            $tenant,
            $branch,
            Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Cotton Secret', 'sku' => 'SEC-1', 'barcode' => '7770001']),
            Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Secret Buyer', 'phone' => '+8801999000111']),
        ];
    });
}

describe('access', function (): void {
    it('needs a session', function (): void {
        $this->getJson('/app/api/pos/products?q=shirt')->assertUnauthorized();
        $this->postJson('/app/pos/sales', [])->assertUnauthorized();
    });

    it('needs pos.sell on every endpoint', function (): void {
        $keeper = $this->world->user('Stock Keeper');
        $this->actingAs($keeper)->get('/app/pos')->assertForbidden();
        $this->getJson('/app/api/pos/products?q=shirt')->assertForbidden();
        $this->getJson('/app/api/pos/customers?q=rahim')->assertForbidden();
        $this->getJson('/app/api/pos/today')->assertForbidden();
        $this->postJson('/app/api/pos/authorize', ['login' => 'x', 'secret' => 'y', 'permission' => 'pos.discount_override'])->assertForbidden();
        $this->postJson('/app/pos/sales', posRegisterSale($this->world, $this->shirt))->assertForbidden();
        expect(Sale::query()->count())->toBe(0);
    });
});

describe('page', function (): void {
    it('renders the register with methods, settings and the cashier cap', function (): void {
        $this->actingAs($this->world->user('Cashier'))->get('/app/pos')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('pos/register', false)
                ->where('branch.id', $this->world->branch->id)
                ->where('discountCapBp', 0)
                ->where('settings.tax_inclusive', false)
                ->has('paymentMethods', 4) // exchange credit is not a tender here
                ->where('customer', null));
    });

    it('pre-attaches ?customer= of this tenant only', function (): void {
        $rahim = $this->world->customer(['name' => 'Rahim', 'shop_name' => 'Rahim Store']);
        [, , , $foreign] = posForeignTenant();

        $this->actingAs($this->world->user('Cashier'))->get('/app/pos?customer='.$rahim->uuid)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('pos/register', false)
                ->where('customer.uuid', $rahim->uuid)
                ->where('customer.shop_name', 'Rahim Store')
                ->missing('customer.pin_hash'));

        $this->get('/app/pos?customer='.$foreign->uuid)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('pos/register', false)->where('customer', null));
    });
});

describe('product lookup', function (): void {
    it('returns an exact barcode hit on its own, without cost', function (): void {
        $json = $this->actingAs($this->world->user('Cashier'))->getJson('/app/api/pos/products?q=8901001')->assertOk()->json();

        expect($json['exact'])->toBeTrue()
            ->and($json['items'])->toHaveCount(1)
            ->and($json['items'][0]['variants'][0]['id'])->toBe($this->shirt->id)
            ->and($json['items'][0]['variants'][0]['stock'])->toBe(10_000)
            ->and(json_encode($json))->not->toContain('cost');
    });

    it('groups variants into their family with stock at this branch only', function (): void {
        $tenant = $this->world->tenant;
        $family = ProductFamily::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Panjabi Classic', 'code' => 'PJ']);
        $sizes = collect(['M', 'L'])->map(fn (string $size) => VariantOptionValue::factory()->create(['tenant_id' => $tenant->id, 'value' => $size]));
        $m = $this->world->product(145_000, 50_000, ['family_id' => $family->id, 'option1_value_id' => $sizes[0]->id, 'name' => 'Panjabi Classic M', 'sku' => 'PJ-M']);
        $l = $this->world->product(145_000, 50_000, ['family_id' => $family->id, 'option1_value_id' => $sizes[1]->id, 'name' => 'Panjabi Classic L', 'sku' => 'PJ-L']);
        $this->world->stock($m, 3, 50_000);

        // Stock in another branch of the same tenant does not count here.
        $other = app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->for($tenant)->create(['code' => 'DF2']));
        app(TenantContext::class)->run($tenant, $other, fn () => Transaction::run(fn () => app(StockLedger::class)->receive(
            $l, $other, Quantity::ofUnits(7), Money::ofPaisa(50_000), StockMovementType::Opening, null, ['type' => 'test', 'id' => 1], now(),
        )));
        app(TenantContext::class)->set($tenant, $this->world->branch);

        $json = $this->actingAs($this->world->user('Cashier'))->getJson('/app/api/pos/products?q=panjabi')->assertOk()->json();

        expect($json['exact'])->toBeFalse()->and($json['items'])->toHaveCount(1);
        $item = $json['items'][0];
        expect($item['kind'])->toBe('family')
            ->and(array_column($item['variants'], 'option1'))->toBe(['M', 'L'])
            ->and(array_column($item['variants'], 'stock'))->toBe([3_000, 0]);
    });

    it('never shows another tenant\'s products', function (): void {
        posForeignTenant();

        $json = $this->actingAs($this->world->user('Cashier'))->getJson('/app/api/pos/products?q=Cotton')->assertOk()->json();
        expect(array_column($json['items'], 'name'))->toBe(['Cotton Shirt']);
        expect($this->getJson('/app/api/pos/products?q=7770001')->json('items'))->toBe([]);
    });
});

describe('customer lookup', function (): void {
    it('finds this tenant\'s customers and shows the card', function (): void {
        $rahim = $this->world->customer(['name' => 'Rahim Uddin', 'shop_name' => 'Rahim Cloth', 'pin_hash' => 'x']);
        posForeignTenant();

        $hits = $this->actingAs($this->world->user('Cashier'))->getJson('/app/api/pos/customers?q=Rahim')->assertOk()->json('customers');
        expect($hits)->toHaveCount(1)->and($hits[0]['shop_name'])->toBe('Rahim Cloth');
        expect($this->getJson('/app/api/pos/customers?q=Secret')->json('customers'))->toBe([]);

        $card = $this->getJson('/app/api/pos/customers/'.$rahim->uuid)->assertOk()->json('customer');
        // PIN *state* only (04_TRD §3.9 customers-min: has_pin / pin_locked), never the hash.
        expect($card)->toMatchArray(['id' => $rahim->id, 'balance' => 0, 'advance' => 0, 'credit_limit' => null, 'pin_status' => 'set'])
            ->and(json_encode($card))->not->toContain('pin_hash');
    });

    it('404s for another tenant\'s customer and for the walk-in', function (): void {
        [, , , $foreign] = posForeignTenant();

        $this->actingAs($this->world->user('Cashier'))->getJson('/app/api/pos/customers/'.$foreign->uuid)->assertNotFound();
        $this->getJson('/app/api/pos/customers/'.$this->world->walkIn->uuid)->assertNotFound();
    });
});

describe('completing a sale', function (): void {
    it('completes through CompleteSale and returns the number and change', function (): void {
        $res = $this->actingAs($this->world->user('Cashier'))->postJson('/app/pos/sales', posRegisterSale($this->world, $this->shirt))->assertCreated();

        expect($res->json('sale.number'))->toBe('DF1-SALE-26000001')
            ->and($res->json('sale.total'))->toBe(150_000)
            ->and($res->json('sale.change'))->toBe(50_000)
            ->and($res->json('sale.flags'))->toBe([]);
        $sale = Sale::query()->sole();
        expect($sale->origin->value)->toBe('web')->and($sale->sold_by)->toBe($this->world->user('Cashier')->id);
    });

    it('returns the same sale for a resubmit of the same bill', function (): void {
        $body = posRegisterSale($this->world, $this->shirt);
        $first = $this->actingAs($this->world->user('Cashier'))->postJson('/app/pos/sales', $body)->assertCreated();
        $again = $this->postJson('/app/pos/sales', $body)->assertOk();

        expect($again->json('sale.id'))->toBe($first->json('sale.id'))
            ->and(Sale::query()->count())->toBe(1);
    });

    it('flags a total that differs from the screen but still completes', function (): void {
        $res = $this->actingAs($this->world->user('Cashier'))
            ->postJson('/app/pos/sales', posRegisterSale($this->world, $this->shirt, ['expected_total' => 149_000]))
            ->assertCreated();

        expect($res->json('sale.flags'))->toBe(['price_mismatch']);
    });

    it('refuses a discount above the cashier\'s cap with a translated 422', function (): void {
        $body = posRegisterSale($this->world, $this->shirt, [
            'lines' => [['product_id' => $this->shirt->id, 'qty' => 1000, 'discount' => ['type' => 'percent_bp', 'value' => 1000]]],
            'expected_total' => 135_000,
        ]);

        $this->actingAs($this->world->user('Cashier'))->postJson('/app/pos/sales', $body)
            ->assertStatus(422)
            ->assertJsonPath('code', 'discount_cap_exceeded')
            ->assertJsonPath('message', __('pos.errors.discount_cap_exceeded'));
        expect(Sale::query()->count())->toBe(0);
    });

    it('accepts that discount with a manager approval token', function (): void {
        $manager = $this->world->user('Manager');
        $token = $this->actingAs($this->world->user('Cashier'))->postJson('/app/api/pos/authorize', [
            'login' => $manager->email, 'secret' => 'password', 'permission' => 'pos.discount_override',
        ])->assertOk()->assertJsonPath('manager.id', $manager->id)->json('token');

        $body = posRegisterSale($this->world, $this->shirt, [
            'lines' => [['product_id' => $this->shirt->id, 'qty' => 1000, 'discount' => ['type' => 'percent_bp', 'value' => 1000]]],
            'expected_total' => 135_000,
            'approval_token' => $token,
        ]);
        $this->postJson('/app/pos/sales', $body)->assertCreated()->assertJsonPath('sale.total', 135_000);
    });

    it('refuses a due for the walk-in', function (): void {
        $body = posRegisterSale($this->world, $this->shirt, ['tenders' => [['payment_method_id' => $this->world->methods['cash']->id, 'amount' => 100_000]]]);

        $this->actingAs($this->world->user('Cashier'))->postJson('/app/pos/sales', $body)
            ->assertStatus(422)
            ->assertJsonPath('code', 'credit_limit_exceeded');
    });

    it('allows a due for a named customer', function (): void {
        $rahim = $this->world->customer();
        $body = posRegisterSale($this->world, $this->shirt, [
            'customer_id' => $rahim->id,
            'tenders' => [['payment_method_id' => $this->world->methods['cash']->id, 'amount' => 100_000]],
        ]);

        $this->actingAs($this->world->user('Cashier'))->postJson('/app/pos/sales', $body)->assertCreated()->assertJsonPath('sale.due', 50_000);
        expect($rahim->refresh()->balance->paisa)->toBe(50_000);
    });

    it('validates products, customers and methods against this tenant', function (): void {
        [, , $product, $customer] = posForeignTenant();

        $this->actingAs($this->world->user('Cashier'))->postJson('/app/pos/sales', posRegisterSale($this->world, $product, ['customer_id' => $customer->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.product_id', 'customer_id']);
        $this->postJson('/app/pos/sales', posRegisterSale($this->world, $this->shirt, ['customer_id' => $this->world->walkIn->id]))
            ->assertJsonValidationErrors(['customer_id']);
        $this->postJson('/app/pos/sales', posRegisterSale($this->world, $this->shirt, ['client_uuid' => 'nope', 'lines' => []]))
            ->assertJsonValidationErrors(['client_uuid', 'lines']);
    });
});

describe('manager approval', function (): void {
    it('rejects wrong credentials and managers without the permission', function (): void {
        $cashier = $this->world->user('Cashier');
        $manager = $this->world->user('Manager');

        $this->actingAs($cashier)->postJson('/app/api/pos/authorize', ['login' => $manager->email, 'secret' => 'wrong', 'permission' => 'pos.discount_override'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
        $other = $this->world->user('Accountant');
        $this->postJson('/app/api/pos/authorize', ['login' => $other->email, 'secret' => 'password', 'permission' => 'pos.discount_override'])
            ->assertStatus(422)->assertJsonPath('code', 'not_allowed');
        $this->postJson('/app/api/pos/authorize', ['login' => $manager->email, 'secret' => 'password', 'permission' => 'users.manage'])
            ->assertJsonValidationErrors(['permission']);
    });

    it('does not accept a manager of another tenant', function (): void {
        [$foreignTenant] = posForeignTenant();
        $foreignManager = app(TenantContext::class)->withoutTenancy(fn () => User::factory()->create(['tenant_id' => $foreignTenant->id]));

        $this->actingAs($this->world->user('Cashier'))->postJson('/app/api/pos/authorize', [
            'login' => $foreignManager->email, 'secret' => 'password', 'permission' => 'pos.discount_override',
        ])->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
    });

    it('binds the token to the cashier who asked and lets it expire', function (): void {
        $approval = app(ManagerApproval::class);
        $manager = $this->world->user('Manager');
        $cashier = $this->world->user('Cashier');
        $token = $approval->issue($manager, $cashier);

        expect($approval->resolve($token, $cashier)?->id)->toBe($manager->id)
            ->and($approval->resolve($token, $this->world->user('Owner')))->toBeNull()
            ->and($approval->resolve('garbage', $cashier))->toBeNull();

        $this->travel(ManagerApproval::TTL_SECONDS + 1)->seconds();
        expect($approval->resolve($token, $cashier))->toBeNull();

        $this->actingAs($cashier)->postJson('/app/pos/sales', posRegisterSale($this->world, $this->shirt, ['approval_token' => $token]))
            ->assertStatus(422)->assertJsonPath('code', 'approval_expired');
    });
});

describe('today', function (): void {
    it('sums the cashier\'s sales today', function (): void {
        $this->actingAs($this->world->user('Cashier'))->postJson('/app/pos/sales', posRegisterSale($this->world, $this->shirt))->assertCreated();

        $json = $this->getJson('/app/api/pos/today')->assertOk()->json();
        expect($json['count'])->toBe(1)
            ->and($json['total'])->toBe(150_000)
            ->and($json['methods'])->toBe([['name' => 'Cash', 'amount' => 150_000]])
            ->and($json['recent'][0]['number'])->toBe('DF1-SALE-26000001');

        $this->actingAs($this->world->user('Owner'))->getJson('/app/api/pos/today')->assertJsonPath('count', 0);
    });
});
