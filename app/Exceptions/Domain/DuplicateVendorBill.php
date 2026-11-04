<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** The vendor already has a live purchase with this bill number (vendor_ref). */
final class DuplicateVendorBill extends DomainException
{
    public function __construct(public readonly string $ref, public readonly string $existingNumber)
    {
        parent::__construct(__('purchases.duplicate_bill', ['ref' => $ref, 'number' => $existingNumber]));
    }
}
