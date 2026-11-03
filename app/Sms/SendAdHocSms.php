<?php

declare(strict_types=1);

namespace App\Sms;

use App\Exceptions\Domain\InvalidAdminData;
use App\Exceptions\Domain\SmsNotConfigured;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Customers\CustomerNumber;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * The messaging hub's composer (/app/messages, "Send"): one free-text SMS to one customer, a customer
 * group, every customer with a due, or every customer. Each reachable recipient gets its own logged
 * `messages` row and SendSms job (through the SmsGateway, ADR 0007); the body's {name}, {customer},
 * {shop}, {due}, {balance} and {number} are filled per customer. Needs settings.manage (MessagePolicy::send).
 * The audit row records the audience and counts only: never the text or a phone number.
 */
final readonly class SendAdHocSms
{
    public const string EVENT = 'manual';

    public const int MAX_BODY = 1000;

    /** @var list<string> */
    public const array TARGETS = ['customer', 'group', 'due', 'all'];

    /** The placeholders the composer offers as chips. */
    public const array VARIABLES = ['name', 'shop', 'due', 'number'];

    public function __construct(private TenantContext $context, private MessageContext $messages) {}

    /**
     * @param  array{target: string, customer?: string|null, group_id?: int|null}  $audience  `customer` is the customer's uuid
     * @return array{queued: int, skipped: int}
     */
    public function handle(User $actor, array $audience, string $body): array
    {
        Gate::forUser($actor)->authorize('send', Message::class);

        $body = trim($body);
        if ($body === '') {
            throw InvalidAdminData::required('Message');
        }
        if (mb_strlen($body) > self::MAX_BODY) {
            throw InvalidAdminData::invalid(sprintf('The message is longer than %d characters.', self::MAX_BODY));
        }

        $settings = SmsSettings::fromTenant($this->context->tenant());
        if ($settings->driver === 'null') {
            throw SmsNotConfigured::make();
        }

        $queued = $skipped = 0;
        foreach ($this->recipients($audience)->lazyById(200) as $customer) {
            $text = Templates::render($body, $this->values($customer));
            if (SendSms::enqueue(self::EVENT, $customer->phone, $text, $customer, $customer) instanceof Message) {
                $queued++;
            } else {
                $skipped++;
            }
        }

        Audit::log('sms.ad_hoc_sent', $this->context->tenant(), [
            'target' => $audience['target'],
            'group_id' => $audience['group_id'] ?? null,
            'queued' => $queued,
            'skipped' => $skipped,
        ], $actor);

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /**
     * The customers an audience reaches: named (not walk-in), active, not merged away, with a phone.
     *
     * @param  array{target: string, customer?: string|null, group_id?: int|null}  $audience
     * @return Builder<Customer>
     */
    public function recipients(array $audience): Builder
    {
        $query = Customer::query()
            ->where('is_walk_in', false)
            ->where('is_active', true)
            ->whereNull('merged_into_id')
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        return match ($audience['target']) {
            'customer' => $query->where('uuid', (string) ($audience['customer'] ?? '')),
            'group' => $query->where('customer_group_id', (int) ($audience['group_id'] ?? 0)),
            'due' => $query->where('balance', '>', 0),
            'all' => $query,
            default => throw InvalidAdminData::invalid('Choose who receives the message.'),
        };
    }

    /** @return array<string, string> */
    public function values(Customer $customer): array
    {
        $number = $customer->customer_no !== null ? CustomerNumber::format((string) $customer->customer_no) : '';

        return $this->messages->values($customer, ['name' => $customer->name, 'number' => $number]);
    }
}
