<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the stock ledger. Every query is tenant-scoped; the branch is explicit, so reports
 * may ask for another branch of the same tenant (the caller authorizes reports.all_branches).
 */
final readonly class StockQueries
{
    public function onHand(Product $product, Branch $branch): Quantity
    {
        return BranchStock::query()->withoutBranchScope()
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->first()
            ->qty_on_hand ?? Quantity::zero();
    }

    /** @return array<int, Quantity> product id → on hand */
    public function onHandByProduct(Branch $branch): array
    {
        $result = [];
        foreach (BranchStock::query()->withoutBranchScope()->where('branch_id', $branch->id)->orderBy('product_id')->get() as $row) {
            $result[$row->product_id] = $row->qty_on_hand;
        }

        return $result;
    }

    /** Σ qty_remaining × unit_cost over the branch's batches, each batch rounded half-up to the paisa. */
    public function valuation(Branch $branch): Money
    {
        $value = $this->batches($branch)->value(DB::raw('COALESCE(SUM(ROUND(qty_remaining * unit_cost, 0)), 0)'));

        return Money::ofPaisa((int) $value);
    }

    /** @return array<int, Money> product id → stock value */
    public function valuationByProduct(Branch $branch): array
    {
        $rows = $this->batches($branch)
            ->groupBy('product_id')
            ->orderBy('product_id')
            ->select('product_id', DB::raw('SUM(ROUND(qty_remaining * unit_cost, 0)) AS stock_value'))
            ->toBase()
            ->get();

        $result = [];
        foreach ($rows as $row) {
            /** @var object{product_id: int|string, stock_value: int|string} $row */
            $result[(int) $row->product_id] = Money::ofPaisa((int) $row->stock_value);
        }

        return $result;
    }

    /**
     * The product's movements in the branch, oldest first, each with the running on-hand balance.
     *
     * @return list<array{movement: StockMovement, balance: Quantity}>
     */
    public function movements(Product $product, Branch $branch): array
    {
        $movements = StockMovement::query()->withoutBranchScope()
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->select('stock_movements.*', DB::raw('SUM(qty) OVER (ORDER BY id) AS running_balance'))
            ->orderBy('id')
            ->get();

        $result = [];
        foreach ($movements as $movement) {
            $balance = $movement->getAttribute('running_balance');
            $result[] = ['movement' => $movement, 'balance' => Quantity::parse(is_string($balance) ? $balance : '0')];
        }

        return $result;
    }

    /**
     * Stocked products at or below their reorder level in the branch. A reorder level of 0 means
     * "not monitored", so those products never appear.
     *
     * @return list<array{product: Product, on_hand: Quantity}>
     */
    public function lowStock(Branch $branch): array
    {
        $products = Product::query()
            ->leftJoin('branch_stocks', static function ($join) use ($branch): void {
                $join->on('branch_stocks.product_id', '=', 'products.id')->where('branch_stocks.branch_id', '=', $branch->id);
            })
            ->where('products.track_stock', true)
            ->where('products.reorder_level', '>', 0)
            ->whereRaw('COALESCE(branch_stocks.qty_on_hand, 0) <= products.reorder_level')
            ->select('products.*', DB::raw('COALESCE(branch_stocks.qty_on_hand, 0) AS on_hand'))
            ->orderBy('products.name')
            ->get();

        $result = [];
        foreach ($products as $product) {
            $onHand = $product->getAttribute('on_hand');
            $result[] = ['product' => $product, 'on_hand' => Quantity::parse(is_string($onHand) ? $onHand : '0')];
        }

        return $result;
    }

    /** @return Builder<StockBatch> */
    private function batches(Branch $branch): Builder
    {
        return StockBatch::query()->withoutBranchScope()
            ->where('branch_id', $branch->id)
            ->where('qty_remaining', '>', 0);
    }
}
