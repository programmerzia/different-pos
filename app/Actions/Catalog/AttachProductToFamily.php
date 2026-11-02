<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\User;
use App\Models\VariantOptionValue;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Groups a standalone product into a family as one size/colour combination (e.g. legacy "Shirt M" + "Shirt L"). */
final readonly class AttachProductToFamily
{
    public function handle(User $actor, ProductFamily $family, Product $product, int $option1ValueId, ?int $option2ValueId = null): Product
    {
        return Transaction::run(function () use ($actor, $family, $product, $option1ValueId, $option2ValueId): Product {
            $family = ProductFamily::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->findOrFail($product->id);

            if ($product->family_id !== null) {
                throw InvalidCatalogData::invalid('The product already belongs to a family.');
            }
            if ($family->option1_id === null) {
                throw InvalidCatalogData::invalid('The family has no variant options.');
            }
            if (($family->option2_id === null) !== ($option2ValueId === null)) {
                throw InvalidCatalogData::invalid('Give one value per option of the family.');
            }

            $v1 = $this->value($option1ValueId, $family->option1_id);
            $v2 = $option2ValueId === null ? null : $this->value($option2ValueId, (int) $family->option2_id);

            $taken = Product::withTrashed()->where('family_id', $family->id)
                ->where('option1_value_id', $v1->id)
                ->when(! $v2 instanceof VariantOptionValue, fn ($q) => $q->whereNull('option2_value_id'), fn ($q) => $q->where('option2_value_id', $v2?->id))
                ->exists();
            if ($taken) {
                throw InvalidCatalogData::invalid('The family already has a product for this combination.');
            }

            $product->fill([
                'family_id' => $family->id,
                'option1_value_id' => $v1->id,
                'option2_value_id' => $v2?->id,
                'variant_label' => $v2 instanceof VariantOptionValue ? $v1->value.' / '.$v2->value : $v1->value,
                'updated_by' => $actor->id,
            ]);
            $product->save();

            Audit::log('product.attached_to_family', $product, ['family' => $family->code], $actor);

            return $product->refresh();
        });
    }

    private function value(int $id, int $optionId): VariantOptionValue
    {
        $value = VariantOptionValue::query()->findOrFail($id);
        if ($value->variant_option_id !== $optionId) {
            throw InvalidCatalogData::invalid('A selected value does not belong to the family\'s option.');
        }

        return $value;
    }
}
