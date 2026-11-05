<?php

declare(strict_types=1);

namespace App\Sms;

use App\Exceptions\Domain\InvalidAdminData;
use App\Models\Customer;
use App\Support\Customers\CustomerNumber;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who an ad-hoc SMS broadcast reaches (the composer's counts, SendSmsBroadcast's recipient count and
 * FanOutSmsBroadcast's recipients use the same query): named (not walk-in), active, not merged away
 * customers whose phone is a valid Bangladeshi mobile number (the BdPhone rule, so the counts and the
 * cost estimate leave out numbers SendSms would skip).
 */
final readonly class BroadcastAudience
{
    public const string EVENT = 'manual';

    public const int MAX_BODY = 1000;

    /** @var list<string> */
    public const array TARGETS = ['customer', 'group', 'due', 'all'];

    /** The placeholders the composer offers as chips. */
    public const array VARIABLES = ['name', 'shop', 'due', 'number'];

    /** Everything values() fills. A broadcast has no invoice or payment, so {invoice}, {total} or {link} cannot be here. */
    public const array FILLED = [...Templates::ALWAYS_FILLED, 'number'];

    /**
     * BdPhone::toE164 on the digits of the stored phone: 01XXXXXXXXX, 8801XXXXXXXXX, 008801XXXXXXXXX or
     * 1XXXXXXXXX with an operator digit 3-9. (Customer phones are stored normalised, so the SQL check
     * on Latin digits matches the PHP one.)
     */
    private const string VALID_MOBILE_SQL = "REGEXP_REPLACE(phone, '[^0-9]', '') REGEXP '^(0|880|00880)?1[3-9][0-9]{8}$'";

    public function __construct(private MessageContext $messages) {}

    /**
     * @param  array{target: string, customer?: string|null, group_id?: int|null}  $audience  `customer` is the customer's uuid
     * @return Builder<Customer>
     */
    public function recipients(array $audience): Builder
    {
        $query = $this->reachable();

        return match ($audience['target']) {
            'customer' => $query->where('uuid', (string) ($audience['customer'] ?? '')),
            'group' => $query->where('customer_group_id', (int) ($audience['group_id'] ?? 0)),
            'due' => $query->where('balance', '>', 0),
            'all' => $query,
            default => throw InvalidAdminData::invalid((string) __('sms.broadcast.no_target')),
        };
    }

    /** @return Builder<Customer> */
    public function reachable(): Builder
    {
        return Customer::query()
            ->where('is_walk_in', false)
            ->where('is_active', true)
            ->whereNull('merged_into_id')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereRaw(self::VALID_MOBILE_SQL);
    }

    /** @return array<string, string> the template values for one recipient */
    public function values(Customer $customer): array
    {
        $number = $customer->customer_no !== null ? CustomerNumber::format((string) $customer->customer_no) : '';

        return $this->messages->values($customer, ['name' => $customer->name, 'number' => $number]);
    }
}
