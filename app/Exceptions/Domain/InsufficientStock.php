<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Support\Quantity\Quantity;

/** An issue asks for more than the branch holds and the branch does not allow negative stock. */
final class InsufficientStock extends DomainException
{
    public static function for(int $productId, Quantity $requested, Quantity $available): self
    {
        return new self(sprintf(
            'Insufficient stock for product %d: %s requested, %s available.',
            $productId,
            $requested->toDecimalString(),
            $available->toDecimalString(),
        ));
    }

    public static function inBatch(int $batchId, Quantity $requested, Quantity $available): self
    {
        return new self(sprintf(
            'Insufficient stock in batch %d: %s requested, %s available.',
            $batchId,
            $requested->toDecimalString(),
            $available->toDecimalString(),
        ));
    }
}
