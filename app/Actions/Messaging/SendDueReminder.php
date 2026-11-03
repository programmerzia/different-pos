<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Enums\MessageChannel;
use App\Models\Customer;
use App\Models\User;
use App\Sms\Templates;

/**
 * Manual SMS due reminder, for one customer or a whole dues list: SendDirectMessage on the SMS channel
 * with the tenant's due_reminder template. Customers without a due, a phone or a name (walk-in) are
 * skipped. The template's "enabled" toggle governs automatic messages only.
 */
final readonly class SendDueReminder
{
    public function __construct(private SendDirectMessage $direct) {}

    /**
     * @param  iterable<Customer>  $customers
     * @return array{queued: int, skipped: int}
     */
    public function handle(User $actor, iterable $customers): array
    {
        $result = $this->direct->handle($actor, $customers, MessageChannel::Sms, Templates::DUE_REMINDER);

        return ['queued' => $result['queued'], 'skipped' => $result['skipped']];
    }
}
