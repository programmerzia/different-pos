<?php

declare(strict_types=1);

namespace App\Sms;

use App\Exceptions\Domain\SmsNotConfigured;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

/**
 * Hook for the statement screen (`messaging.sms_statement`): authorizes and queues the SMS.
 * Throws SmsNotConfigured when the gateway is not set up. Returns null when the customer has no valid mobile number.
 */
final readonly class SendCustomerStatement
{
    public function __construct(private TenantContext $context) {}

    public function handle(User $actor, Customer $customer, string $summary): ?Message
    {
        Gate::forUser($actor)->authorize('send', Message::class);

        if (! SmsSettings::fromTenant($this->context->tenant())->isConfigured()) {
            throw SmsNotConfigured::make();
        }

        return SendSms::statement($customer, $summary);
    }
}
