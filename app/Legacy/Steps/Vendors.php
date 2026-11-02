<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Legacy\ImportContext;
use App\Legacy\Transform\Phone;
use App\Legacy\Transform\Text;
use App\Models\Vendor;

/** Step 5: store_vendor_list -> vendors (phone to E.164; invalid or duplicate -> NULL + report), plus one "Unknown vendor". */
final readonly class Vendors implements Step
{
    public const string TABLE = 'store_vendor_list';

    public const string UNKNOWN = 'unknown_vendor';

    public function name(): string
    {
        return 'Vendors';
    }

    public function run(ImportContext $context): void
    {
        /** @var array<string, true> $phones */
        $phones = Vendor::query()->withTrashed()->whereNotNull('phone')->pluck('phone')->flip()->map(fn (): bool => true)->all();

        $context->select(self::TABLE, ['id', 'store_id'], ['name', 'mobile', 'email', 'address', 'description'])
            ->whereIn('store_id', $context->stores())
            ->orderBy('id')
            ->chunk($context->options->chunk, function ($rows) use ($context, &$phones): void {
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    if ($context->ids->has(self::TABLE, $id)) {
                        $context->skipped();

                        continue;
                    }

                    $raw = Text::orNull($context->text($row->mobile, self::TABLE, $id, 'mobile'));
                    $phone = Phone::toE164($raw);
                    if ($raw !== null && $phone === null) {
                        $context->report->add('invalid_phone', self::TABLE, $id, 'Vendor phone is not a valid Bangladeshi mobile number; left empty');
                    } elseif ($phone !== null && isset($phones[$phone])) {
                        $context->report->add('duplicate_phone', self::TABLE, $id, 'Vendor phone already used by another vendor; left empty');
                        $phone = null;
                    }
                    if ($phone !== null) {
                        $phones[$phone] = true;
                    }

                    $vendor = new Vendor;
                    $vendor->forceFill([
                        'name' => Text::limit($context->text($row->name, self::TABLE, $id, 'name'), 150) ?? "Vendor #{$id}",
                        'phone' => $phone,
                        'email' => Text::email($context->text($row->email, self::TABLE, $id, 'email')),
                        'address' => Text::limit(Text::orNull($context->text($row->address, self::TABLE, $id, 'address')), 500),
                        'notes' => Text::orNull($context->text($row->description, self::TABLE, $id, 'description')),
                        'is_active' => true,
                        'legacy_id' => $id,
                    ])->save();

                    $context->ids->put(self::TABLE, (int) $row->store_id, $id, $vendor->id);
                    $context->created();
                }
            });

        $unknown = $context->ids->get(self::UNKNOWN, 0);
        if ($unknown === null) {
            $vendor = new Vendor;
            $vendor->forceFill(['name' => 'Unknown vendor', 'notes' => 'Legacy stock-ins without a known vendor', 'is_active' => true])->save();
            $unknown = $vendor->id;
            $context->ids->put(self::UNKNOWN, 0, 0, $unknown);
            $context->created();
        }
        $context->setLookup(self::UNKNOWN, $unknown);
    }
}
