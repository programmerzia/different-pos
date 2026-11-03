<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Legacy\ImportContext;
use App\Legacy\Transform\LegacyDate;
use App\Legacy\Transform\Phone;
use App\Legacy\Transform\Text;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Support\Customers\CustomerNumber;
use stdClass;

/**
 * Step 6: customer groups and customers. customer_no is allocated in ascending legacy id order.
 * A duplicate phone within the tenant is merged into the oldest customer: the newer row is kept as a
 * soft-deleted shell (merged_into_id) and its legacy id maps to the survivor, so its sales follow.
 * The legacy customer credentials (plaintext) are never selected.
 */
final readonly class CustomerGroupsAndCustomers implements Step
{
    public const string TABLE = 'store_customer_list';

    public const string GROUPS = 'store_customer_group';

    public const string WALK_IN = 'walk_in_customer';

    public function __construct(private CustomerNumber $numbers) {}

    public function name(): string
    {
        return 'CustomerGroupsAndCustomers';
    }

    public function run(ImportContext $context): void
    {
        $this->groups($context);

        /** @var array<string, int> $phones phone => customer id */
        $phones = Customer::query()->withTrashed()->whereNotNull('phone')->pluck('id', 'phone')->map(fn (mixed $id): int => (int) $id)->all();

        $context->select(self::TABLE, ['id', 'store_id', 'customer_group_id', 'dob', 'marriage_date', 'created_at'], ['name', 'shop_name', 'mobile', 'email', 'address'])
            ->whereIn('store_id', $context->stores())
            ->orderBy('id')
            ->chunk($context->options->chunk, function ($rows) use ($context, &$phones): void {
                foreach ($rows as $row) {
                    $this->customer($context, $row, $phones);
                }
            });

        $this->walkIn($context);
    }

    private function groups(ImportContext $context): void
    {
        $used = $context->legacy()->table(self::TABLE)->whereIn('store_id', $context->stores())
            ->where('customer_group_id', '>', 0)->distinct()->orderBy('customer_group_id')->pluck('customer_group_id')
            ->map(fn (mixed $id): int => (int) $id)->all();
        $legacy = $context->select(self::GROUPS, ['id', 'status'], ['title'])->whereIn('id', $used)->get()->keyBy('id');

        foreach ($used as $groupId) {
            if ($context->ids->has(self::GROUPS, $groupId)) {
                $context->skipped();

                continue;
            }

            $row = $legacy->get($groupId);
            if ($row === null) {
                // The group id points nowhere in legacy (DIFFERENT FASHION's group 2): those customers
                // are simply ungrouped, so they import with no group rather than a made-up one.
                $count = $context->legacy()->table(self::TABLE)->whereIn('store_id', $context->stores())->where('customer_group_id', $groupId)->count();
                $context->report->add('orphan_group', self::GROUPS, $groupId, "Group does not exist in legacy; {$count} customers imported without a group");
                $context->ids->put(self::GROUPS, 0, $groupId, 0);

                continue;
            }
            $name = Text::limit($context->text($row->title, self::GROUPS, $groupId, 'title'), 100) ?? "Group {$groupId}";

            $group = CustomerGroup::query()->withTrashed()->where('name', $name)->first();
            if (! $group instanceof CustomerGroup) {
                $group = new CustomerGroup;
                $group->forceFill(['name' => $name, 'is_active' => $row->status === 'Active', 'legacy_id' => $groupId])->save();
                $context->created();
            }
            $context->ids->put(self::GROUPS, 0, $groupId, $group->id);
        }
    }

    /** @param array<string, int> $phones */
    private function customer(ImportContext $context, stdClass $row, array &$phones): void
    {
        $id = (int) $row->id;
        if ($context->ids->has(self::TABLE, $id)) {
            $context->skipped();

            return;
        }

        $name = Text::limit($context->text($row->name, self::TABLE, $id, 'name'), 150);
        $shop = Text::limit(Text::orNull($context->text($row->shop_name, self::TABLE, $id, 'shop_name')), 150);
        $raw = Text::orNull($context->text($row->mobile, self::TABLE, $id, 'mobile'));
        $phone = Phone::toE164($raw);
        if ($raw !== null && $phone === null) {
            $context->report->add('invalid_phone', self::TABLE, $id, 'Customer phone is not a valid Bangladeshi mobile number; left empty');
        }
        if ($name === null) {
            $name = $shop ?? 'Customer L'.$id;
            $context->report->add('blank_name', self::TABLE, $id, "Customer has no name; named [{$name}]");
        }
        $address = Text::limit(Text::orNull($context->text($row->address, self::TABLE, $id, 'address')), 500);
        $groupId = (int) $row->customer_group_id > 0 ? ($context->ids->get(self::GROUPS, (int) $row->customer_group_id) ?: null) : null;

        $customer = new Customer;
        $customer->forceFill([
            'customer_group_id' => $groupId,
            'name' => $name,
            'shop_name' => $shop,
            'email' => Text::email($context->text($row->email, self::TABLE, $id, 'email')),
            'dob' => LegacyDate::date($row->dob),
            'anniversary_date' => LegacyDate::date($row->marriage_date),
            'address_line' => $address,
            // Geo ids are not matched yet (no geo tables in Phase D), so a free-text address is unverified.
            'location_verified' => $address === null,
            'member_since' => LegacyDate::date($row->created_at),
            'is_active' => true,
            'legacy_id' => $id,
        ]);

        $survivor = $phone === null ? null : ($phones[$phone] ?? null);
        if ($survivor !== null) {
            // Merge: keep the oldest. The shell keeps the legacy details for review but no number and no phone.
            $customer->forceFill(['merged_into_id' => $survivor, 'is_active' => false, 'notes' => 'Merged into an older customer with the same phone during the legacy import'])->save();
            $customer->delete();
            $context->ids->put(self::TABLE, (int) $row->store_id, $id, $survivor);
            $kept = Customer::query()->withTrashed()->whereKey($survivor)->first(['customer_no', 'legacy_id']);
            $context->report->add('merged_duplicate_phone', self::TABLE, $id, sprintf(
                'Same phone as an older customer; merged into customer no %s (legacy #%s), sales remapped',
                $kept->customer_no ?? '?', $kept->legacy_id ?? '-',
            ));
            $context->created();

            return;
        }

        $customer->forceFill(['phone' => $phone, 'customer_no' => (int) $this->numbers->allocate()])->save();
        if ($phone !== null) {
            $phones[$phone] = $customer->id;
        }
        $context->ids->put(self::TABLE, (int) $row->store_id, $id, $customer->id);
        $context->created();
    }

    /** One walk-in customer per tenant, created after the legacy customers so they get the first numbers. */
    private function walkIn(ImportContext $context): void
    {
        $id = Customer::query()->where('is_walk_in', true)->value('id');
        if (! is_int($id)) {
            $customer = new Customer;
            $customer->forceFill([
                'name' => 'Walk-in customer',
                'is_walk_in' => true,
                'is_active' => true,
                'customer_no' => (int) $this->numbers->allocate(),
            ])->save();
            $id = $customer->id;
            $context->created();
        }

        $context->setLookup(self::WALK_IN, $id);
    }
}
