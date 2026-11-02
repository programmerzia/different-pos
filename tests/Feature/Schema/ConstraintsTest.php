<?php

declare(strict_types=1);

use App\Enums\CardStatus;
use App\Enums\MessageStatus;
use App\Models\Customer;
use App\Models\MembershipCard;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use App\Models\StockBatch;
use App\Models\Tenant;
use App\Models\VariantOptionValue;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->ctx = app(TenantContext::class);
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->branch = makeBranch($this->a);
    /** Runs with tenancy bypassed, as seeders and importers do. */
    $this->raw = fn (callable $callback): mixed => $this->ctx->withoutTenancy($callback);
});

it('allows one active membership card per customer', function (): void {
    ($this->raw)(function (): void {
        $customer = Customer::factory()->create(['tenant_id' => $this->a->id]);
        $other = Customer::factory()->create(['tenant_id' => $this->a->id]);

        MembershipCard::factory()->create(['tenant_id' => $this->a->id, 'customer_id' => $customer->id, 'issue_no' => 1]);
        MembershipCard::factory()->create(['tenant_id' => $this->a->id, 'customer_id' => $other->id, 'issue_no' => 1]);
        MembershipCard::factory()->create([
            'tenant_id' => $this->a->id, 'customer_id' => $customer->id, 'issue_no' => 2, 'status' => CardStatus::Lost,
        ]);

        expect(fn () => MembershipCard::factory()->create([
            'tenant_id' => $this->a->id, 'customer_id' => $customer->id, 'issue_no' => 3,
        ]))->toThrow(QueryException::class);
    });
});

it('keeps sku and barcode unique per tenant only', function (): void {
    ($this->raw)(function (): void {
        Product::factory()->create(['tenant_id' => $this->a->id, 'sku' => 'TSHIRT-M', 'barcode' => '8901234']);
        Product::factory()->create(['tenant_id' => $this->b->id, 'sku' => 'TSHIRT-M', 'barcode' => '8901234']);

        expect(fn () => Product::factory()->create(['tenant_id' => $this->a->id, 'sku' => 'TSHIRT-M']))
            ->toThrow(QueryException::class)
            ->and(fn () => Product::factory()->create(['tenant_id' => $this->a->id, 'barcode' => '8901234']))
            ->toThrow(QueryException::class);

        // several products without a barcode are fine
        Product::factory()->count(2)->create(['tenant_id' => $this->a->id, 'barcode' => null]);
    });
});

it('reserves the MC barcode prefix for membership cards', function (): void {
    ($this->raw)(function (): void {
        expect(fn () => Product::factory()->create(['tenant_id' => $this->a->id, 'barcode' => 'MC1000001701']))
            ->toThrow(QueryException::class);
    });
});

it('allows one product per variant combination in a family', function (): void {
    ($this->raw)(function (): void {
        $family = ProductFamily::factory()->create(['tenant_id' => $this->a->id]);
        $medium = VariantOptionValue::factory()->create(['tenant_id' => $this->a->id]);
        $large = VariantOptionValue::factory()->create(['tenant_id' => $this->a->id]);

        $variant = Product::factory()->create([
            'tenant_id' => $this->a->id, 'family_id' => $family->id, 'option1_value_id' => $medium->id,
        ]);
        Product::factory()->create(['tenant_id' => $this->a->id, 'family_id' => $family->id, 'option1_value_id' => $large->id]);

        expect(DB::table('products')->where('id', $variant->id)->value('variant_key'))->toBe((string) $medium->id)
            ->and(fn () => Product::factory()->create([
                'tenant_id' => $this->a->id, 'family_id' => $family->id, 'option1_value_id' => $medium->id,
            ]))->toThrow(QueryException::class);

        // standalone products (no family) are not constrained
        Product::factory()->count(2)->create(['tenant_id' => $this->a->id]);
    });
});

it('keeps customer phone unique per tenant only', function (): void {
    ($this->raw)(function (): void {
        Customer::factory()->create(['tenant_id' => $this->a->id, 'phone' => '+8801711111111']);
        Customer::factory()->create(['tenant_id' => $this->b->id, 'phone' => '+8801711111111']);

        expect(fn () => Customer::factory()->create(['tenant_id' => $this->a->id, 'phone' => '+8801711111111']))
            ->toThrow(QueryException::class);
    });
});

it('stores the shop name and hides the pin hash', function (): void {
    $customer = ($this->raw)(fn () => Customer::factory()->create([
        'tenant_id' => $this->a->id, 'shop_name' => 'Rahman Traders', 'pin_hash' => 'secret-hash',
    ]));

    expect(Customer::withoutGlobalScopes()->find($customer->id)?->shop_name)->toBe('Rahman Traders')
        ->and($customer->toArray())->not->toHaveKey('pin_hash');
});

it('rejects a batch with more remaining than received or a negative remainder', function (int $received, int $remaining): void {
    ($this->raw)(function () use ($received, $remaining): void {
        expect(fn () => StockBatch::factory()->create([
            'tenant_id' => $this->a->id, 'branch_id' => $this->branch->id,
            'qty_received' => Quantity::ofUnits($received), 'qty_remaining' => Quantity::ofUnits($remaining),
        ]))->toThrow(QueryException::class);
    });
})->with([[5, 6], [5, -1]]);

it('accepts a fully consumed batch', function (): void {
    $batch = ($this->raw)(fn () => StockBatch::factory()->create([
        'tenant_id' => $this->a->id, 'branch_id' => $this->branch->id,
        'qty_received' => Quantity::ofUnits(5), 'qty_remaining' => Quantity::zero(),
    ]));

    expect($batch->exists)->toBeTrue();
});

it('rejects negative sale totals and non-positive payments', function (): void {
    ($this->raw)(function (): void {
        $base = ['tenant_id' => $this->a->id, 'branch_id' => $this->branch->id];

        expect(fn () => Sale::factory()->create($base + ['total' => Money::ofPaisa(-1)]))->toThrow(QueryException::class)
            ->and(fn () => Sale::factory()->create($base + ['paid' => Money::ofPaisa(-1)]))->toThrow(QueryException::class)
            ->and(fn () => Payment::factory()->create($base + ['amount' => Money::zero()]))->toThrow(QueryException::class);
    });
});

it('rejects over-returned sale lines and empty return lines', function (): void {
    ($this->raw)(function (): void {
        expect(fn () => SaleItem::factory()->create([
            'qty' => Quantity::ofUnits(2), 'qty_returned' => Quantity::ofUnits(3),
        ]))->toThrow(QueryException::class)
            ->and(fn () => SaleItem::factory()->create(['qty' => Quantity::zero()]))->toThrow(QueryException::class)
            ->and(fn () => SaleReturnItem::factory()->create(['qty' => Quantity::zero()]))->toThrow(QueryException::class);
    });
});

it('keeps sale numbers and client uuids unique per tenant', function (): void {
    ($this->raw)(function (): void {
        $base = ['tenant_id' => $this->a->id, 'branch_id' => $this->branch->id];
        Sale::factory()->create($base + ['number' => 'DF1-R1-26000001', 'client_uuid' => '0190c8a0-0000-7000-8000-000000000001']);
        Sale::factory()->create($base + ['number' => null, 'client_uuid' => '0190c8a0-0000-7000-8000-000000000002']);
        Sale::factory()->create($base + ['number' => null, 'client_uuid' => '0190c8a0-0000-7000-8000-000000000003']);

        expect(fn () => Sale::factory()->create($base + ['number' => 'DF1-R1-26000001']))->toThrow(QueryException::class)
            ->and(fn () => Sale::factory()->create($base + ['client_uuid' => '0190c8a0-0000-7000-8000-000000000001']))
            ->toThrow(QueryException::class);
    });
});

it('rejects values outside an enum column', function (): void {
    $row = [
        'tenant_id' => $this->a->id, 'phone' => '+8801711111111', 'body' => 'hi', 'driver' => 'log', 'event' => 'manual',
    ];

    DB::table('messages')->insert($row + ['status' => MessageStatus::Sent->value]);

    expect(fn () => DB::table('messages')->insert($row + ['status' => 'bogus']))->toThrow(QueryException::class);
});

it('round-trips money and quantity as integers', function (): void {
    $product = ($this->raw)(fn () => Product::factory()->create([
        'tenant_id' => $this->a->id, 'sale_price' => Money::ofPaisa(129900), 'reorder_level' => Quantity::parse('2.5'),
    ]));

    expect(DB::table('products')->where('id', $product->id)->value('sale_price'))->toBe(129900)
        ->and(Product::withoutGlobalScopes()->find($product->id)?->reorder_level->milli)->toBe(2500);
});

it('loads database defaults into enum casts', function (): void {
    $message = ($this->raw)(fn () => Message::factory()->create(['tenant_id' => $this->a->id]));

    expect($message->refresh()->status)->toBe(MessageStatus::Queued);
});
