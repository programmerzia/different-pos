<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\DeleteOutcome;
use App\Models\Product;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use Illuminate\Support\Facades\DB;

/** Deletes a product, or only deactivates it when any document, stock row or movement references it. */
final readonly class DeleteProduct
{
    /** @var list<string> */
    private const array REFERENCING_TABLES = [
        'sale_items', 'sale_return_items', 'purchase_items', 'stock_adjustment_items',
        'stock_batches', 'stock_movements', 'branch_stocks',
    ];

    public function handle(User $actor, Product $product): DeleteOutcome
    {
        return Transaction::run(function () use ($actor, $product): DeleteOutcome {
            $product = Product::query()->findOrFail($product->id);

            if ($this->isReferenced($product)) {
                $product->is_active = false;
                $product->updated_by = $actor->id;
                $product->save();
                Audit::log('product.deactivated', $product, ['sku' => $product->sku], $actor);

                return DeleteOutcome::Deactivated;
            }

            $product->updated_by = $actor->id;
            $product->save();
            $product->delete();
            Audit::log('product.deleted', $product, ['sku' => $product->sku], $actor);

            return DeleteOutcome::Deleted;
        });
    }

    private function isReferenced(Product $product): bool
    {
        return array_any(self::REFERENCING_TABLES, fn ($table) => DB::table($table)->where('product_id', $product->id)->exists());
    }
}
