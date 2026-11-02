<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\Product;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Turns a variant back into a standalone product (keeps its SKU, barcode, prices and stock). */
final readonly class DetachProductFromFamily
{
    public function handle(User $actor, Product $product): Product
    {
        return Transaction::run(function () use ($actor, $product): Product {
            $product = Product::query()->findOrFail($product->id);
            if ($product->family_id === null) {
                throw InvalidCatalogData::invalid('The product does not belong to a family.');
            }

            $familyId = $product->family_id;
            $product->fill(['family_id' => null, 'option1_value_id' => null, 'option2_value_id' => null, 'variant_label' => null, 'updated_by' => $actor->id]);
            $product->save();

            Audit::log('product.detached_from_family', $product, ['family_id' => $familyId], $actor);

            return $product->refresh();
        });
    }
}
