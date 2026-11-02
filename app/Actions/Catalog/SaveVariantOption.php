<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\User;
use App\Models\VariantOption;
use App\Models\VariantOptionValue;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/**
 * Creates or updates a variant option (Size, Colour) and upserts its values. A value is matched by
 * its text (case-insensitive); a blank code is derived: the full text for "Size" (up to 10 letters),
 * else the first two letters (three, then a digit suffix, on a clash). Values omitted from the call stay.
 */
final readonly class SaveVariantOption
{
    /**
     * @param  array{name?: string, name_bn?: string|null, sort_order?: int, values?: list<array{value: string, value_bn?: string|null, code?: string|null, sort_order?: int}>}  $data
     */
    public function handle(User $actor, array $data, ?VariantOption $option = null): VariantOption
    {
        return Transaction::run(function () use ($actor, $data, $option): VariantOption {
            $option = $option instanceof VariantOption ? VariantOption::query()->findOrFail($option->id) : null;
            $name = trim((string) ($data['name'] ?? $option->name ?? ''));
            if ($name === '') {
                throw InvalidCatalogData::required('Name');
            }

            $same = VariantOption::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->when($option instanceof VariantOption, fn ($q) => $q->whereKeyNot($option?->id))
                ->first();
            if ($same instanceof VariantOption && $option instanceof VariantOption) {
                throw InvalidCatalogData::duplicate('variant option', $name);
            }
            $option ??= $same ?? new VariantOption;
            $created = ! $option->exists;

            $option->fill(['name' => $name, ...array_intersect_key($data, array_flip(['name_bn', 'sort_order']))]);
            $option->save();

            foreach (($data['values'] ?? []) as $index => $row) {
                $this->saveValue($option, $row, $index);
            }

            Audit::log($created ? 'variant_option.created' : 'variant_option.updated', $option, ['name' => $name], $actor);

            return $option->load('values');
        });
    }

    /** @param array{value: string, value_bn?: string|null, code?: string|null, sort_order?: int} $row */
    private function saveValue(VariantOption $option, array $row, int $index): void
    {
        $text = trim($row['value']);
        if ($text === '') {
            throw InvalidCatalogData::required('Value');
        }

        $value = VariantOptionValue::query()
            ->where('variant_option_id', $option->id)
            ->whereRaw('LOWER(value) = ?', [mb_strtolower($text)])
            ->first() ?? new VariantOptionValue(['variant_option_id' => $option->id]);

        $code = strtoupper(trim((string) ($row['code'] ?? '')));
        if ($code === '') {
            $code = $value->exists ? $value->code : $this->deriveCode($option, $text);
        }
        if (preg_match('/^[A-Z0-9]{1,10}$/', $code) !== 1) {
            throw InvalidCatalogData::invalid('A value code is 1 to 10 letters or digits.');
        }
        $clash = VariantOptionValue::query()->where('variant_option_id', $option->id)->where('code', $code)
            ->when($value->exists, fn ($q) => $q->whereKeyNot($value->id))->exists();
        if ($clash) {
            throw InvalidCatalogData::duplicate('value code', $code);
        }

        $value->fill([
            'value' => $value->exists ? $value->value : $text,
            'code' => $code,
            'value_bn' => $row['value_bn'] ?? $value->value_bn,
            'sort_order' => $row['sort_order'] ?? ($value->exists ? $value->sort_order : $index),
        ]);
        $value->save();
    }

    private function deriveCode(VariantOption $option, string $text): string
    {
        $letters = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $text));
        if ($letters === '') {
            throw InvalidCatalogData::invalid('Give this value a code (Latin letters or digits).');
        }

        $isSize = strtolower($option->name) === 'size';
        $candidates = $isSize ? [substr($letters, 0, 10)] : [substr($letters, 0, 2), substr($letters, 0, 3)];
        foreach ($candidates as $candidate) {
            if (! VariantOptionValue::query()->where('variant_option_id', $option->id)->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        $base = substr($letters, 0, 2);
        for ($n = 2; ; $n++) {
            if (! VariantOptionValue::query()->where('variant_option_id', $option->id)->where('code', $base.$n)->exists()) {
                return $base.$n;
            }
        }
    }
}
