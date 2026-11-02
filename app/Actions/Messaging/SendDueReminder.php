<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Exceptions\Domain\SmsNotConfigured;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Sms\MessageContext;
use App\Sms\SendSms;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

/**
 * Manual due reminder, for one customer or a whole dues list. Customers without a due, a phone or
 * a name (walk-in) are skipped. The template's "enabled" toggle governs automatic messages only.
 */
final readonly class SendDueReminder
{
    public function __construct(private TenantContext $context, private MessageContext $messages) {}

    /**
     * @param  iterable<Customer>  $customers
     * @return array{queued: int, skipped: int}
     */
    public function handle(User $actor, iterable $customers): array
    {
        Gate::forUser($actor)->authorize('send', Message::class);

        $settings = SmsSettings::fromTenant($this->context->tenant());
        if ($settings->driver === 'null') {
            throw SmsNotConfigured::make();
        }

        $queued = $skipped = 0;
        foreach ($customers as $customer) {
            if (! $this->messages->reachable($customer) || $customer->balance->paisa <= 0) {
                $skipped++;

                continue;
            }

            $body = Templates::render($settings->body(Templates::DUE_REMINDER), $this->messages->values($customer));
            if (SendSms::enqueue(Templates::DUE_REMINDER, $customer->phone, $body, $customer, $customer) instanceof Message) {
                $queued++;
            } else {
                $skipped++;
            }
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }
}
