<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\DeleteOutcome;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Deletes a category, or only deactivates it when children, products or families reference it. */
final readonly class DeleteCategory
{
    public function handle(User $actor, Category $category): DeleteOutcome
    {
        return Transaction::run(function () use ($actor, $category): DeleteOutcome {
            $category = Category::query()->findOrFail($category->id);
            $referenced = Category::query()->where('parent_id', $category->id)->exists()
                || Product::withTrashed()->where('category_id', $category->id)->exists()
                || ProductFamily::withTrashed()->where('category_id', $category->id)->exists();

            if ($referenced) {
                $category->is_active = false;
                $category->updated_by = $actor->id;
                $category->save();
                Audit::log('category.deactivated', $category, [], $actor);

                return DeleteOutcome::Deactivated;
            }

            $category->updated_by = $actor->id;
            $category->save();
            $category->delete();
            Audit::log('category.deleted', $category, [], $actor);

            return DeleteOutcome::Deleted;
        });
    }
}
