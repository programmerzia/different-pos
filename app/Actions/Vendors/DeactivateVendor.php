<?php

declare(strict_types=1);

namespace App\Actions\Vendors;

use App\Models\User;
use App\Models\Vendor;
use App\Support\Database\Transaction;

/** Deactivates a vendor (purchases and balance history stay). Idempotent. */
final readonly class DeactivateVendor
{
    public function handle(User $actor, Vendor $vendor): Vendor
    {
        return Transaction::run(function () use ($actor, $vendor): Vendor {
            $vendor = Vendor::query()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            if ($vendor->is_active) {
                $vendor->is_active = false;
                $vendor->updated_by = $actor->id;
                $vendor->save();
            }

            return $vendor;
        });
    }
}
