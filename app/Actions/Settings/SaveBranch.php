<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Exceptions\Domain\InvalidAdminData;
use App\Models\Branch;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Creates or updates a branch. The creator is assigned to a new branch so it shows up in their switcher. */
final readonly class SaveBranch
{
    /**
     * @param  array{code?: string, name?: string, phone?: string|null, address?: string|null, receipt_header?: string|null, receipt_footer?: string|null, is_active?: bool, settings?: array<string, mixed>}  $data
     */
    public function handle(User $actor, array $data, ?Branch $branch = null): Branch
    {
        return Transaction::run(function () use ($actor, $data, $branch): Branch {
            $branch = $branch instanceof Branch ? Branch::query()->findOrFail($branch->id) : null;
            $created = ! $branch instanceof Branch;
            $branch ??= new Branch;

            $code = strtoupper(trim((string) ($data['code'] ?? $branch->code ?? '')));
            $name = trim((string) ($data['name'] ?? $branch->name ?? ''));
            if ($code === '' || $name === '') {
                throw InvalidAdminData::required($code === '' ? 'Code' : 'Name');
            }
            if (Branch::query()->withTrashed()->where('code', $code)->when(! $created, fn ($q) => $q->whereKeyNot($branch->id))->exists()) {
                throw InvalidAdminData::duplicate('branch', $code);
            }

            $active = (bool) ($data['is_active'] ?? ($created ? true : $branch->is_active));
            if (! $created && $branch->is_active && ! $active
                && ! Branch::query()->where('is_active', true)->whereKeyNot($branch->id)->exists()) {
                throw InvalidAdminData::invalid('At least one branch must stay active.');
            }

            $settings = $branch->settings ?? [];
            foreach (array_intersect_key($data['settings'] ?? [], Branch::DEFAULT_SETTINGS) as $key => $value) {
                $settings[$key] = $this->setting($key, $value);
            }

            $branch->fill(['code' => $code, 'name' => $name, 'is_active' => $active, 'settings' => $settings]);
            foreach (['phone', 'address', 'receipt_header', 'receipt_footer'] as $field) {
                if (array_key_exists($field, $data)) {
                    $branch->{$field} = trim((string) $data[$field]) ?: null;
                }
            }
            $branch->save();

            if ($created) {
                $actor->branches()->syncWithoutDetaching([$branch->id => ['is_default' => false]]);
            }
            Audit::log($created ? 'branch.created' : 'branch.updated', $branch, ['code' => $code, 'is_active' => $active], $actor);

            return $branch;
        });
    }

    private function setting(string $key, mixed $value): mixed
    {
        return match ($key) {
            'allow_negative_stock', 'auto_print' => (bool) $value,
            'batch_selection' => in_array($value, ['auto', 'manual'], true) ? $value : throw InvalidAdminData::invalid('Unknown batch selection.'),
            'paper' => in_array($value, ['80', '58', 'a4'], true) ? $value : throw InvalidAdminData::invalid('Unknown paper size.'),
            default => max(0, (int) $value), // cash_rounding (paisa)
        };
    }
}
