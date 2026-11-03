<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Enums\ProductType;
use App\Legacy\ImportContext;
use App\Legacy\Transform\Text;
use App\Models\Product;
use App\Models\Unit;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * Step 7: store_product_list -> products (tenant-wide catalog). product_code -> sku. The same code in
 * another legacy store merges into the first product (one product, stock per branch); a code already
 * used in the same store or by a non-legacy product gets "-{legacy_id}". A deleted legacy product referenced by history becomes an
 * inactive, soft-deleted placeholder (see placeholder()).
 */
final readonly class Products implements Step
{
    public const string TABLE = 'store_product_list';

    public const string UNIT = 'unit_pcs';

    public function name(): string
    {
        return 'Products';
    }

    public function run(ImportContext $context): void
    {
        $unit = Unit::query()->where('short_name', 'pcs')->first();
        if (! $unit instanceof Unit) {
            $unit = Unit::query()->create(['name' => 'Piece', 'short_name' => 'pcs', 'allows_decimal' => false, 'is_active' => true]);
            $context->created();
        }
        $context->setLookup(self::UNIT, $unit->id);

        // lowercased SKU => the product that holds it, its legacy store (0 = unknown) and whether it came from legacy.
        /** @var array<string, array{id: int, store: int, legacy: bool}> $skus */
        $skus = [];
        $stores = DB::table('legacy_id_maps')->where('entity', self::TABLE)->orderBy('id')->pluck('legacy_store_id', 'new_id');
        foreach (Product::query()->withTrashed()->get(['id', 'sku', 'legacy_id']) as $existing) {
            $skus[mb_strtolower((string) $existing->sku)] = ['id' => $existing->id, 'store' => (int) ($stores[$existing->id] ?? 0), 'legacy' => $existing->legacy_id !== null];
        }

        $context->select(self::TABLE, ['id', 'store_id', 'category_id', 'brand_id', 'vendor_id', 'quantity', 'min_sale_qty', 'stock_out_alert', 'status'], ['product_name', 'product_code'], ['mrp', 'buy_price'])
            ->whereIn('store_id', $context->stores())
            ->orderBy('id')
            ->chunk($context->options->chunk, function ($rows) use ($context, $unit, &$skus): void {
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    if ($context->ids->has(self::TABLE, $id)) {
                        $context->skipped();

                        continue;
                    }

                    $code = Text::limit($context->text($row->product_code, self::TABLE, $id, 'product_code'), 50) ?? 'L'.$row->store_id.'-'.$id;
                    $storeId = (int) $row->store_id;
                    $sku = $code;
                    $holder = $skus[mb_strtolower($sku)] ?? null;
                    if ($holder !== null && $holder['legacy'] && $holder['store'] !== $storeId) {
                        // The same article stocked in another shop: one product, stock per branch. The first
                        // product keeps its name, prices and active flag.
                        $context->ids->put(self::TABLE, $storeId, $id, $holder['id']);
                        $context->skipped();
                        $context->report->add('merged_product', self::TABLE, $id, "Product code [{$code}] from store {$storeId} merged into product #{$holder['id']} imported from store {$holder['store']}");

                        continue;
                    }
                    if ($holder !== null) {
                        $sku = $code.'-'.$id;
                        $context->report->add('duplicate_sku', self::TABLE, $id, "Product code [{$code}] already used in the tenant; imported as SKU [{$sku}]");
                    }

                    $cost = Money::parseRounded((string) ($row->buy_price ?? '0'));
                    if ($cost->isZero()) {
                        $context->report->add('cost_zero', self::TABLE, $id, "Product [{$sku}] has buy price 0; cost price imported as 0");
                    }

                    $vendorId = (int) $row->vendor_id > 0 ? $context->ids->get(Vendors::TABLE, (int) $row->vendor_id) : null;
                    if ((int) $row->vendor_id > 0 && $vendorId === null) {
                        $context->report->add('orphan_vendor', self::TABLE, $id, "Vendor #{$row->vendor_id} does not exist; no default vendor");
                    }

                    $product = new Product;
                    $product->forceFill([
                        'type' => ProductType::Standard,
                        'name' => Text::limit($context->text($row->product_name, self::TABLE, $id, 'product_name'), 200) ?? "Product #{$id}",
                        'sku' => $sku,
                        'category_id' => (int) $row->category_id > 0 ? $context->ids->get('store_category_list', (int) $row->category_id) : null,
                        'brand_id' => (int) $row->brand_id > 0 ? $context->ids->get('store_brand_list', (int) $row->brand_id) : null,
                        'unit_id' => $unit->id,
                        'default_vendor_id' => $vendorId,
                        'sale_price' => Money::parseRounded((string) ($row->mrp ?? '0'))->abs(),
                        'cost_price' => $cost->abs(),
                        'min_sale_qty' => Quantity::ofUnits(max(1, (int) $row->min_sale_qty)),
                        'reorder_level' => Quantity::ofUnits(max(0, (int) $row->stock_out_alert)),
                        'track_stock' => true,
                        'is_active' => $row->status === 'Active',
                        'legacy_id' => $id,
                    ])->save();

                    $skus[mb_strtolower($sku)] = ['id' => $product->id, 'store' => $storeId, 'legacy' => true];
                    $context->ids->put(self::TABLE, $storeId, $id, $product->id);
                    $context->created();
                }
            });
    }

    /** New id of a legacy product; a missing (hard-deleted) one becomes a reported placeholder. */
    public static function resolve(ImportContext $context, int $legacyId, string $fromTable, int $fromId): int
    {
        $id = $context->ids->get(self::TABLE, $legacyId);
        if ($id !== null) {
            return $id;
        }

        $product = new Product;
        $product->forceFill([
            'type' => ProductType::Standard,
            'name' => "Deleted product #{$legacyId}",
            'sku' => "L-DELETED-{$legacyId}",
            'unit_id' => $context->lookup(self::UNIT),
            'sale_price' => Money::zero(),
            'cost_price' => Money::zero(),
            'min_sale_qty' => Quantity::ofUnits(1),
            'reorder_level' => Quantity::zero(),
            'is_active' => false,
            'legacy_id' => $legacyId,
        ])->save();
        $product->delete();

        $context->ids->put(self::TABLE, 0, $legacyId, $product->id);
        $context->report->add('orphan_product', $fromTable, $fromId, "Product #{$legacyId} no longer exists; linked to placeholder [{$product->sku}]");

        return $product->id;
    }
}
