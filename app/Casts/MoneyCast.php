<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * BIGINT paisa column ↔ Money. Accepts only Money (or null) on write: never a float or a string.
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
final class MoneyCast implements CastsAttributes
{
    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
            return Money::ofPaisa((int) $value);
        }

        throw new InvalidArgumentException(sprintf('Column [%s] must hold integer paisa.', $key));
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException(sprintf('Attribute [%s] must be set with a Money instance.', $key));
        }

        return $value->paisa;
    }
}
