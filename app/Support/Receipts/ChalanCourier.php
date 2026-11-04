<?php

declare(strict_types=1);

namespace App\Support\Receipts;

use App\Models\Courier;
use App\Models\Sale;

/**
 * Which courier prints on a delivery chalan (print-only, never written to the sale):
 * the one picked for this print (?courier=, an active courier of this tenant, anything else ignored),
 * else the sale's own courier text, else the tenant's default courier, else nothing (hand-fill).
 */
final readonly class ChalanCourier
{
    /** @return array{name: string, phone: string|null}|null */
    public static function resolve(Sale $sale, ?int $requestedId): ?array
    {
        if ($requestedId !== null) {
            $picked = Courier::query()->where('is_active', true)->find($requestedId);
            if ($picked instanceof Courier) {
                return ['name' => $picked->name, 'phone' => $picked->phone];
            }
        }

        $own = trim((string) $sale->courier_name);
        if ($own !== '') {
            $known = Courier::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($own)])->first();

            return ['name' => $own, 'phone' => $known?->phone];
        }

        $default = Courier::query()->where('is_default', true)->where('is_active', true)->first();

        return $default instanceof Courier ? ['name' => $default->name, 'phone' => $default->phone] : null;
    }
}
