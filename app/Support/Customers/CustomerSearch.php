<?php

declare(strict_types=1);

namespace App\Support\Customers;

use App\Models\Customer;
use Illuminate\Support\Collection;

/**
 * Customer picker query: by customer number (Latin or Bangla digits, spaces ignored; exact when
 * 8 digits, prefix from 4), by phone suffix, and by name (EN/BN) or shop name. Current tenant only.
 */
final readonly class CustomerSearch
{
    private const int LIMIT = 20;

    /** @return Collection<int, CustomerSearchResult> */
    public function search(string $term, int $limit = self::LIMIT): Collection
    {
        $term = trim($term);
        $limit = max(1, min($limit, self::LIMIT));
        if ($term === '') {
            return new Collection;
        }

        $digits = CustomerNumber::normalize($term);
        $numeric = preg_match('/^[0-9]+$/', $digits) === 1;

        $query = Customer::query()->where('is_walk_in', false);

        if ($numeric) {
            $national = ltrim(str_starts_with($digits, '880') ? substr($digits, 3) : $digits, '0');
            $query->where(function ($q) use ($digits, $national): void {
                if (strlen($digits) === 8) {
                    $q->orWhere('customer_no', (int) $digits);
                } elseif (strlen($digits) >= 4 && strlen($digits) < 8) {
                    $q->orWhere('customer_no', 'like', $digits.'%');
                }
                if (strlen($national) >= 3) {
                    $q->orWhere('phone', 'like', '%'.$national)->orWhere('alt_phone', 'like', '%'.$national);
                }
            });
            if (strlen($digits) === 8) {
                $query->orderByRaw('customer_no = ? desc', [(int) $digits]);
            }
        } else {
            $likeRaw = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $likeRaw)->orWhere('name_bn', 'like', $likeRaw)
                ->orWhere('shop_name', 'like', $likeRaw));
        }

        return $query->orderBy('name')->limit($limit)->get()->map(static fn (Customer $c): CustomerSearchResult => new CustomerSearchResult(
            $c->id,
            $c->uuid,
            $c->customer_no === null ? null : CustomerNumber::format((string) $c->customer_no),
            $c->name,
            $c->name_bn,
            $c->shop_name,
            $c->phone,
            $c->is_active,
        ))->values();
    }
}
