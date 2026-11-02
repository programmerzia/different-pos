<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\DeleteOutcome;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Deletes a brand, or only deactivates it when products or families reference it. */
final readonly class DeleteBrand
{
    public function handle(User $actor, Brand $brand): DeleteOutcome
    {
        return Transaction::run(function () use ($actor, $brand): DeleteOutcome {
            $brand = Brand::query()->findOrFail($brand->id);
            $referenced = Product::withTrashed()->where('brand_id', $brand->id)->exists()
                || ProductFamily::withTrashed()->where('brand_id', $brand->id)->exists();

            if ($referenced) {
                $brand->is_active = false;
                $brand->updated_by = $actor->id;
                $brand->save();
                Audit::log('brand.deactivated', $brand, [], $actor);

                return DeleteOutcome::Deactivated;
            }

            $brand->updated_by = $actor->id;
            $brand->save();
            $brand->delete();
            Audit::log('brand.deleted', $brand, [], $actor);

            return DeleteOutcome::Deleted;
        });
    }
}
