<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Models\Customer;

/** Another customer of the tenant already uses this phone number; carries that customer for the "open it" link. */
final class DuplicateCustomerPhone extends DomainException
{
    public function __construct(public readonly Customer $existing, public readonly string $phone)
    {
        parent::__construct(sprintf('Phone %s already belongs to customer %s.', $phone, $existing->name));
    }
}
