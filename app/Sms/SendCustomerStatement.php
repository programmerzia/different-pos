<?php

declare(strict_types=1);

namespace App\Sms;

use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Hook for the statement screen (`messaging.sms_statement`): authorizes and queues the SMS.
 * Returns null when the customer has no valid mobile number.
 */
final readonly class SendCustomerStatement
{
    public function handle(User $actor, Customer $customer, string $summary): ?Message
    {
        Gate::forUser($actor)->authorize('send', Message::class);

        return SendSms::statement($customer, $summary);
    }
}
