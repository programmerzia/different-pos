<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Creates or updates a VAT rate (basis points); at most one default per tenant. Name is deduped case-insensitively. */
final readonly class SaveTaxRate
{
    /** @param array{name?: string, rate_bp?: int, is_default?: bool, is_active?: bool} $data */
    public function handle(User $actor, array $data, ?TaxRate $taxRate = null): TaxRate
    {
        return Transaction::run(function () use ($actor, $data, $taxRate): TaxRate {
            $taxRate = $taxRate instanceof TaxRate ? TaxRate::query()->findOrFail($taxRate->id) : null;
            $name = trim((string) ($data['name'] ?? $taxRate->name ?? ''));
            if ($name === '') {
                throw InvalidCatalogData::required('Name');
            }

            $rate = $data['rate_bp'] ?? $taxRate?->rate_bp;
            if ($rate === null) {
                throw InvalidCatalogData::required('Rate');
            }
            if ($rate < 0 || $rate > 10_000) {
                throw InvalidCatalogData::invalid('The rate must be between 0 and 10000 basis points.');
            }

            $same = TaxRate::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->when($taxRate instanceof TaxRate, fn ($q) => $q->whereKeyNot($taxRate?->id))
                ->first();

            if ($same instanceof TaxRate && $taxRate instanceof TaxRate) {
                throw InvalidCatalogData::duplicate('tax rate', $name);
            }
            $taxRate ??= $same;
            $created = ! $taxRate instanceof TaxRate;
            $taxRate ??= new TaxRate;

            $isDefault = (bool) ($data['is_default'] ?? $taxRate->is_default);
            if ($isDefault) {
                foreach (TaxRate::query()->where('is_default', true)->when(! $created, fn ($q) => $q->whereKeyNot($taxRate->id))->get() as $other) {
                    $other->is_default = false;
                    $other->save();
                }
            }

            $taxRate->fill(['name' => $name, 'rate_bp' => $rate, 'is_default' => $isDefault, ...array_intersect_key($data, ['is_active' => true])]);
            $taxRate->save();

            Audit::log($created ? 'tax_rate.created' : 'tax_rate.updated', $taxRate, ['name' => $name, 'rate_bp' => $rate], $actor);

            return $taxRate;
        });
    }
}
