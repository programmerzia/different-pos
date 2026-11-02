<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Money was collected from a customer (dispatched after the transaction). */
final readonly class CustomerPaymentCollected
{
    use Dispatchable;

    public function __construct(public int $paymentId, public int $customerId) {}
}
