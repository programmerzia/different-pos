<?php

declare(strict_types=1);

use App\Actions\Customers\SetCustomerPin;
use App\Enums\MemberAuth;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Fixtures\SalesWorld;

/** @param  array<string, mixed>  $tenantSettings */
function memberWorld(array $tenantSettings = []): SalesWorld
{
    $world = SalesWorld::make($tenantSettings);
    foreach ($world->users as $user) {
        $user->branches()->attach($world->branch);
    }

    return $world;
}

/** @param  array<string, mixed>  $overrides */
function memberSale(SalesWorld $world, Product $product, Customer $customer, string $clientUuid, array $overrides = []): array
{
    return [
        'client_uuid' => $clientUuid,
        'customer_id' => $customer->id,
        'lines' => [['product_id' => $product->id, 'qty' => 1000, 'discount' => ['type' => 'none', 'value' => 0]]],
        'order_discount' => ['type' => 'none', 'value' => 0],
        'tenders' => [['payment_method_id' => $world->methods['cash']->id, 'amount' => 150_000]],
        'expected_total' => 150_000,
        ...$overrides,
    ];
}

beforeEach(function (): void {
    RateLimiter::clear('operator:');
    // The tenant default is `off`; these tests exercise the optional policy unless they say otherwise.
    $this->world = memberWorld(['customer_pin_policy' => 'optional']);
    $this->shirt = $this->world->product(150_000, 60_000, ['name' => 'Cotton Shirt', 'sku' => 'SH-1']);
    $this->world->stock($this->shirt, 20, 60_000);
    $this->member = $this->world->customer(['name' => 'Rahim Uddin', 'phone' => '+8801711223344', 'customer_no' => 10000017]);
    app(SetCustomerPin::class)->handle($this->world->user('Owner'), $this->member, '4827', '4827');
    $this->cart = (string) Str::uuid();
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

describe('verify-pin', function (): void {
    it('answers a member token for the right PIN and PIN state only', function (): void {
        $response = $this->actingAs($this->world->user('Cashier'))
            ->postJson('/app/api/pos/customers/'.$this->member->uuid.'/verify-pin', ['pin' => '4827', 'client_uuid' => $this->cart])
            ->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('attempts_left', 5)->assertJsonPath('expires_in', 600);
        expect($response->json('token'))->toBeString()->not->toBeEmpty();
        expect($response->getContent())->not->toContain('4827')->not->toContain('pin_hash')->not->toContain((string) $this->member->refresh()->pin_hash);
    });

    it('answers wrong with the tries left, then locked', function (): void {
        $this->actingAs($this->world->user('Cashier'));
        $this->postJson('/app/api/pos/customers/'.$this->member->uuid.'/verify-pin', ['pin' => '0000', 'client_uuid' => $this->cart])
            ->assertStatus(422)->assertJsonPath('status', 'wrong')->assertJsonPath('attempts_left', 4)->assertJsonMissingPath('token');
        $this->member->forceFill(['pin_failed_attempts' => 4])->save();
        $this->postJson('/app/api/pos/customers/'.$this->member->uuid.'/verify-pin', ['pin' => '0000', 'client_uuid' => $this->cart])
            ->assertStatus(422)->assertJsonPath('status', 'locked')->assertJsonPath('attempts_left', 0);
        $this->postJson('/app/api/pos/customers/'.$this->member->uuid.'/verify-pin', ['pin' => '4827', 'client_uuid' => $this->cart])
            ->assertStatus(422)->assertJsonPath('status', 'locked');
    });

    it('is rate limited to five tries a minute per customer', function (): void {
        $this->actingAs($this->world->user('Cashier'));
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/app/api/pos/customers/'.$this->member->uuid.'/verify-pin', ['pin' => '4827', 'client_uuid' => $this->cart])->assertOk();
        }
        $this->postJson('/app/api/pos/customers/'.$this->member->uuid.'/verify-pin', ['pin' => '4827', 'client_uuid' => $this->cart])->assertStatus(429);
    });

    it('needs pos.sell and the tenant\'s own active member', function (): void {
        [$foreign] = app(TenantContext::class)->withoutTenancy(function (): array {
            $tenant = Tenant::factory()->create();

            return [Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Secret Buyer', 'pin_hash' => Hash::make('4827')])];
        });
        $body = ['pin' => '4827', 'client_uuid' => $this->cart];

        $this->postJson('/app/api/pos/customers/'.$this->member->uuid.'/verify-pin', $body)->assertUnauthorized();
        $this->actingAs($this->world->user('Stock Keeper'))->postJson('/app/api/pos/customers/'.$this->member->uuid.'/verify-pin', $body)->assertForbidden();
        $this->actingAs($this->world->user('Cashier'))->postJson('/app/api/pos/customers/'.$foreign->uuid.'/verify-pin', $body)->assertNotFound();
        $this->postJson('/app/api/pos/customers/'.$this->world->walkIn->uuid.'/verify-pin', $body)->assertNotFound();
    });
});

describe('set at the register', function (): void {
    it('lets the cashier help a member choose a first PIN, never replace one', function (): void {
        $fresh = $this->world->customer(['name' => 'New Member', 'phone' => '+8801811000999']);
        $this->actingAs($this->world->user('Cashier'));

        $this->postJson('/app/api/pos/customers/'.$fresh->uuid.'/pin', ['pin' => '0999', 'pin_confirmation' => '0999'])
            ->assertStatus(422)->assertJsonPath('code', 'weak');
        $this->postJson('/app/api/pos/customers/'.$fresh->uuid.'/pin', ['pin' => '9316', 'pin_confirmation' => '9316'])
            ->assertOk()->assertJsonPath('pin_status', 'set')->assertJsonMissingPath('pin_hash');
        expect(Hash::check('9316', (string) $fresh->refresh()->pin_hash))->toBeTrue();

        $this->postJson('/app/api/pos/customers/'.$this->member->uuid.'/pin', ['pin' => '9316', 'pin_confirmation' => '9316'])
            ->assertStatus(422)->assertJsonPath('code', 'already_set');
        expect(Hash::check('4827', (string) $this->member->refresh()->pin_hash))->toBeTrue();
    });

    it('shows the PIN state on the register customer card and the policy in the settings, never the hash', function (): void {
        $this->actingAs($this->world->user('Cashier'));
        $card = $this->getJson('/app/api/pos/customers/'.$this->member->uuid)->assertOk()->assertJsonPath('customer.pin_status', 'set');
        expect($card->getContent())->not->toContain('pin_hash')->not->toContain((string) $this->member->refresh()->pin_hash);

        $this->get('/app/pos?customer='.$this->member->uuid)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('settings.pin_policy', 'optional')->where('customer.pin_status', 'set')->missing('customer.pin_hash'));
    });
});

describe('member_auth on the sale', function (): void {
    it('records pin_online with a member token for this customer and cart', function (): void {
        $this->actingAs($this->world->user('Cashier'));
        $token = $this->postJson('/app/api/pos/customers/'.$this->member->uuid.'/verify-pin', ['pin' => '4827', 'client_uuid' => $this->cart])->json('token');

        $this->postJson('/app/pos/sales', memberSale($this->world, $this->shirt, $this->member, $this->cart, ['member_token' => $token]))
            ->assertCreated()->assertJsonPath('sale.member_auth', 'pin_online');
        expect(Sale::query()->sole()->member_auth)->toBe(MemberAuth::PinOnline);
    });

    it('ignores a token for another cart or another customer (optional policy → none)', function (): void {
        $this->actingAs($this->world->user('Cashier'));
        $token = $this->postJson('/app/api/pos/customers/'.$this->member->uuid.'/verify-pin', ['pin' => '4827', 'client_uuid' => (string) Str::uuid()])->json('token');
        $this->postJson('/app/pos/sales', memberSale($this->world, $this->shirt, $this->member, $this->cart, ['member_token' => $token]))
            ->assertCreated()->assertJsonPath('sale.member_auth', 'none');

        $other = $this->world->customer(['name' => 'Other Member']);
        $cart = (string) Str::uuid();
        $token = $this->postJson('/app/api/pos/customers/'.$this->member->uuid.'/verify-pin', ['pin' => '4827', 'client_uuid' => $cart])->json('token');
        $this->postJson('/app/pos/sales', memberSale($this->world, $this->shirt, $other, $cart, ['member_token' => $token]))
            ->assertCreated()->assertJsonPath('sale.member_auth', 'none');
        $this->postJson('/app/pos/sales', memberSale($this->world, $this->shirt, $this->member, (string) Str::uuid(), ['member_token' => 'garbage']))
            ->assertCreated()->assertJsonPath('sale.member_auth', 'none');
    });

    it('records none for a walk-in and for a skipped PIN under the optional policy', function (): void {
        $this->actingAs($this->world->user('Cashier'));
        $body = memberSale($this->world, $this->shirt, $this->member, $this->cart);
        unset($body['customer_id']);
        $this->postJson('/app/pos/sales', $body)->assertCreated()->assertJsonPath('sale.member_auth', 'none');
        $this->postJson('/app/pos/sales', memberSale($this->world, $this->shirt, $this->member, (string) Str::uuid()))->assertCreated()->assertJsonPath('sale.member_auth', 'none');
    });

    it('records override with a manager who holds pos.customer_pin_override', function (): void {
        $manager = $this->world->user('Manager');
        $this->actingAs($this->world->user('Cashier'));
        $approval = $this->postJson('/app/api/pos/authorize', ['login' => $manager->email, 'secret' => 'password', 'permission' => 'pos.customer_pin_override'])->assertOk()->json('token');

        $this->postJson('/app/pos/sales', memberSale($this->world, $this->shirt, $this->member, $this->cart, ['approval_token' => $approval]))
            ->assertCreated()->assertJsonPath('sale.member_auth', 'override');
    });

    it('records no_pin_policy when the policy is off (the default), and the register says so', function (): void {
        app(TenantContext::class)->forget();
        $world = memberWorld();
        $shirt = $world->product(150_000, 60_000, ['sku' => 'SH-OFF']);
        $world->stock($shirt, 5, 60_000);
        $member = $world->customer(['name' => 'Off Member']);

        $this->actingAs($world->user('Cashier'));
        $this->get('/app/pos')->assertInertia(fn (AssertableInertia $page) => $page->where('settings.pin_policy', 'off'));
        $this->postJson('/app/pos/sales', memberSale($world, $shirt, $member, (string) Str::uuid()))->assertCreated()->assertJsonPath('sale.member_auth', 'no_pin_policy');
    });

    it('refuses a member sale without PIN or override when the policy is required (legacy `always` too)', function (): void {
        app(TenantContext::class)->forget();
        $world = memberWorld(['customer_pin_policy' => 'always']);
        $shirt = $world->product(150_000, 60_000, ['sku' => 'SH-REQ']);
        $world->stock($shirt, 5, 60_000);
        $member = $world->customer(['name' => 'Strict Member', 'phone' => '+8801911000111']);
        app(SetCustomerPin::class)->handle($world->user('Owner'), $member, '4827', '4827');

        $this->actingAs($world->user('Cashier'));
        $this->get('/app/pos')->assertInertia(fn (AssertableInertia $page) => $page->where('settings.pin_policy', 'required'));
        $this->postJson('/app/pos/sales', memberSale($world, $shirt, $member, (string) Str::uuid()))
            ->assertStatus(422)->assertJsonPath('code', 'member_pin_required')->assertJsonPath('message', __('pos.errors.member_pin_required'));
        expect(Sale::query()->count())->toBe(0);

        // A walk-in needs nothing; a verified member passes.
        $body = memberSale($world, $shirt, $member, (string) Str::uuid());
        unset($body['customer_id']);
        $this->postJson('/app/pos/sales', $body)->assertCreated()->assertJsonPath('sale.member_auth', 'none');
        $cart = (string) Str::uuid();
        $token = $this->postJson('/app/api/pos/customers/'.$member->uuid.'/verify-pin', ['pin' => '4827', 'client_uuid' => $cart])->json('token');
        $this->postJson('/app/pos/sales', memberSale($world, $shirt, $member, $cart, ['member_token' => $token]))->assertCreated()->assertJsonPath('sale.member_auth', 'pin_online');
    });
});
