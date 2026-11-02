<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\Category;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/**
 * Creates or updates a category (two levels at most). Without $category the name is looked up
 * case-insensitively under the same parent: a hit is updated (and restored if soft-deleted).
 * With $category, a name used by another category under that parent is refused.
 */
final readonly class SaveCategory
{
    /** @param array{name?: string, name_bn?: string|null, parent_id?: int|null, sort_order?: int, is_active?: bool} $data */
    public function handle(User $actor, array $data, ?Category $category = null): Category
    {
        return Transaction::run(function () use ($actor, $data, $category): Category {
            $category = $category instanceof Category ? Category::query()->findOrFail($category->id) : null;
            $name = trim((string) ($data['name'] ?? $category->name ?? ''));
            if ($name === '') {
                throw InvalidCatalogData::required('Name');
            }

            $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $category?->parent_id;
            if ($parentId !== null) {
                $parent = Category::query()->findOrFail($parentId);
                if ($parent->parent_id !== null || ($category instanceof Category && ($parent->id === $category->id || $category->children()->exists()))) {
                    throw InvalidCatalogData::invalid('Categories can be nested two levels deep at most.');
                }
            }

            $same = Category::withTrashed()
                ->where('parent_id', $parentId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->when($category instanceof Category, fn ($q) => $q->whereKeyNot($category?->id))
                ->first();

            if ($same instanceof Category && $category instanceof Category) {
                throw InvalidCatalogData::duplicate('category', $name);
            }
            $category ??= $same;
            $created = ! $category instanceof Category;
            $category ??= new Category;

            $category->fill([
                'name' => $name,
                'parent_id' => $parentId,
                ...array_intersect_key($data, array_flip(['name_bn', 'sort_order', 'is_active'])),
            ]);
            $category->created_by ??= $actor->id;
            $category->updated_by = $actor->id;
            if ($category->trashed()) {
                $category->restore();
            }
            $category->save();

            Audit::log($created ? 'category.created' : 'category.updated', $category, ['name' => $name], $actor);

            return $category;
        });
    }
}
