<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Actions\Sales\CompleteSale;
use App\Actions\Sales\CreateSaleReturn;
use App\Actions\Sales\Data\ReturnLineDraft;
use App\Actions\Sales\Data\SaleDraft;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\User;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;

/**
 * The exact amounts of an exchange before it is saved, read-only: the return valued by CreateSaleReturn::plan
 * and the replacement priced by CompleteSale (VAT, group discount, rounding), so the screen shows what the
 * server will charge. A surplus clears the original invoice's due first (CreateSaleReturn::settle).
 */
final readonly class ExchangeQuote
{
    public function __construct(
        private CreateSaleReturn $returns,
        private CompleteSale $sales,
        private TenantContext $context,
    ) {}

    /**
     * @param  list<ReturnLineDraft>  $returnLines
     * @return array{return_value: int, replacement_total: int, replacement_tax: int, group_discount: int, pays: int, surplus: int, due_cleared: int, gets_back: int, original_due: int, walk_in: bool, lines: list<array{product_id: int, name: string, sku: string, qty: int, total: int, stock: int|null, short: bool}>}
     */
    public function for(User $actor, int $saleId, array $returnLines, SaleDraft $replacement): array
    {
        $plan = $this->returns->plan($saleId, $returnLines);
        $customer = $plan->customer;
        $priced = $this->sales->priceOnly($actor, new SaleDraft(
            lines: $replacement->lines,
            tenders: [],
            customerId: $customer->is_walk_in ? null : $customer->id,
        ));

        $value = $plan->total()->paisa;
        $total = $priced['order']['grand_total'];
        $surplus = max(0, $value - $total);
        $originalDue = $plan->sale->due->paisa;
        $dueCleared = min($originalDue, $surplus);

        return [
            'return_value' => $value,
            'replacement_total' => $total,
            'replacement_tax' => $priced['order']['tax'],
            'group_discount' => $priced['order']['group_discount'],
            'pays' => max(0, $total - $value),
            'surplus' => $surplus,
            'due_cleared' => $dueCleared,
            'gets_back' => $surplus - $dueCleared,
            'original_due' => $originalDue,
            'walk_in' => $customer->is_walk_in,
            'lines' => $this->lines($replacement, $priced['lines']),
        ];
    }

    /**
     * @param  list<array{total: int}>  $priced
     * @return list<array{product_id: int, name: string, sku: string, qty: int, total: int, stock: int|null, short: bool}>
     */
    private function lines(SaleDraft $replacement, array $priced): array
    {
        $ids = array_map(static fn ($line): int => $line->productId, $replacement->lines);
        $products = Product::query()->whereIn('id', $ids)->get()->keyBy('id');
        $stocks = BranchStock::query()
            ->where('branch_id', $this->context->branchId())
            ->whereIn('product_id', $ids)
            ->get()
            ->keyBy('product_id');

        $lines = [];
        foreach ($replacement->lines as $index => $line) {
            /** @var Product $product */
            $product = $products[$line->productId];
            $onHand = $product->track_stock ? ($stocks[$product->id]->qty_on_hand ?? Quantity::zero())->milli : null;
            $lines[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'qty' => $line->qty->milli,
                'total' => $priced[$index]['total'],
                'stock' => $onHand,
                'short' => $onHand !== null && $line->qty->milli > $onHand,
            ];
        }

        return $lines;
    }
}
