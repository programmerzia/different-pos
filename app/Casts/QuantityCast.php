<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Quantity\Quantity;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * DECIMAL(14,3) column ↔ Quantity. Accepts only Quantity (or null) on write.
 *
 * @implements CastsAttributes<Quantity|null, mixed>
 */
final class QuantityCast implements CastsAttributes
{
    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Quantity
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_string($value)) {
            return Quantity::parse((string) $value);
        }

        throw new InvalidArgumentException(sprintf('Column [%s] must hold a DECIMAL(14,3) value.', $key));
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Quantity) {
            throw new InvalidArgumentException(sprintf('Attribute [%s] must be set with a Quantity instance.', $key));
        }

        return $value->toDecimalString();
    }
}
