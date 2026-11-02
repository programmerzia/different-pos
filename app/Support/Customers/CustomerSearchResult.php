<?php

declare(strict_types=1);

namespace App\Support\Customers;

/** One row of the customer picker. */
final readonly class CustomerSearchResult
{
    public function __construct(
        public int $id,
        public string $uuid,
        public ?string $customerNo,
        public string $name,
        public ?string $nameBn,
        public ?string $shopName,
        public ?string $phone,
        public bool $isActive,
    ) {}
}
