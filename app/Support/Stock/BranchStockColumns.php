<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\User;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * The per-branch stock columns of the product and stock lists (mockup "Stock (Main) · Stock (B2) · Total").
 * Users with `reports.all_branches` (the Owner) see every active branch of the tenant; everyone else sees
 * only the current branch, the same rule the reports follow. Reads cross branches with
 * `withoutBranchScope()`; the tenant scope still applies.
 */
final readonly class BranchStockColumns
{
    public function __construct(private TenantContext $context) {}

    /** @return list<Branch> */
    public function branchesFor(User $user): array
    {
        $current = $this->context->branch();
        if (! $user->can('reports.all_branches')) {
            return [$current];
        }

        $branches = Branch::query()->where('is_active', true)->orderBy('id')->get(['id', 'uuid', 'tenant_id', 'code', 'name']);

        return $branches->contains('id', $current->id) ? array_values($branches->all()) : [$current, ...$branches->all()];
    }

    /**
     * @param  list<Branch>  $branches
     * @return list<array{id: int, code: string, name: string, current: bool}>
     */
    public function present(array $branches): array
    {
        $current = $this->context->branchId();

        return array_map(static fn (Branch $b): array => [
            'id' => $b->id,
            'code' => (string) $b->code,
            'name' => $b->name,
            'current' => $b->id === $current,
        ], $branches);
    }

    /**
     * On-hand per product, aligned with `$branches` (milli-units; 0 where a branch never held the product).
     *
     * @param  list<int>  $productIds
     * @param  list<Branch>  $branches
     * @return array<int, list<int>> product id → [milli per branch, in `$branches` order]
     */
    public function onHand(array $productIds, array $branches): array
    {
        if ($productIds === [] || $branches === []) {
            return [];
        }
        $branchIds = array_map(static fn (Branch $b): int => $b->id, $branches);
        $found = [];
        $rows = BranchStock::query()->withoutBranchScope()
            ->whereIn('product_id', $productIds)
            ->whereIn('branch_id', $branchIds)
            ->get(['product_id', 'branch_id', 'qty_on_hand']);
        foreach ($rows as $row) {
            $found[$row->product_id][$row->branch_id] = $row->qty_on_hand->milli;
        }

        $result = [];
        foreach ($productIds as $id) {
            $result[$id] = array_map(static fn (int $b): int => $found[$id][$b] ?? 0, $branchIds);
        }

        return $result;
    }

    /**
     * Σ on-hand per branch over every stock-tracked product the filtered list query matches (not just the page).
     *
     * @param  Builder<Product>  $products  the filtered product query (no select, no order)
     * @param  list<Branch>  $branches
     * @return list<int> milli per branch, in `$branches` order
     */
    public function totals(Builder $products, array $branches): array
    {
        if ($branches === []) {
            return [];
        }
        $branchIds = array_map(static fn (Branch $b): int => $b->id, $branches);
        $ids = (clone $products)->where('products.track_stock', true)->select('products.id');

        $sums = [];
        $rows = BranchStock::query()->withoutBranchScope()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('product_id', $ids)
            ->groupBy('branch_id')
            ->selectRaw('branch_id, COALESCE(SUM(qty_on_hand), 0) AS qty')
            ->toBase()
            ->get();
        foreach ($rows as $row) {
            /** @var object{branch_id: int|string, qty: int|string|float} $row */
            $sums[(int) $row->branch_id] = Quantity::parse((string) $row->qty)->milli;
        }

        return array_map(static fn (int $b): int => $sums[$b] ?? 0, $branchIds);
    }
}
