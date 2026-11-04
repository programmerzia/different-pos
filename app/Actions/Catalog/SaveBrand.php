<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\Brand;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/**
 * Creates or updates a brand; without $brand the name is deduped case-insensitively (a hit is updated).
 * With $rejectDuplicate (inline create) a live brand of the same name is refused instead; a deleted one is restored.
 */
final readonly class SaveBrand
{
    /** @param array{name?: string, is_active?: bool} $data */
    public function handle(User $actor, array $data, ?Brand $brand = null, bool $rejectDuplicate = false): Brand
    {
        return Transaction::run(function () use ($actor, $data, $brand, $rejectDuplicate): Brand {
            $brand = $brand instanceof Brand ? Brand::query()->findOrFail($brand->id) : null;
            $name = trim((string) ($data['name'] ?? $brand->name ?? ''));
            if ($name === '') {
                throw InvalidCatalogData::required('Name');
            }

            $same = Brand::withTrashed()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->when($brand instanceof Brand, fn ($q) => $q->whereKeyNot($brand?->id))
                ->first();

            if ($same instanceof Brand && ($brand instanceof Brand || ($rejectDuplicate && ! $same->trashed()))) {
                throw InvalidCatalogData::duplicate('brand', $name);
            }
            $brand ??= $same;
            $created = ! $brand instanceof Brand;
            $brand ??= new Brand;

            $brand->fill(['name' => $name, ...array_intersect_key($data, ['is_active' => true])]);
            $brand->created_by ??= $actor->id;
            $brand->updated_by = $actor->id;
            if ($brand->trashed()) {
                $brand->restore();
            }
            $brand->save();

            Audit::log($created ? 'brand.created' : 'brand.updated', $brand, ['name' => $name], $actor);

            return $brand;
        });
    }
}
