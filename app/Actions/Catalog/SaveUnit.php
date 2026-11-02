<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\Unit;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Creates or updates a unit; without $unit the name or short name is deduped case-insensitively (a hit is updated). */
final readonly class SaveUnit
{
    /** @param array{name?: string, short_name?: string, allows_decimal?: bool, is_active?: bool} $data */
    public function handle(User $actor, array $data, ?Unit $unit = null): Unit
    {
        return Transaction::run(function () use ($actor, $data, $unit): Unit {
            $unit = $unit instanceof Unit ? Unit::query()->findOrFail($unit->id) : null;
            $name = trim((string) ($data['name'] ?? $unit->name ?? ''));
            $short = trim((string) ($data['short_name'] ?? $unit->short_name ?? ''));
            if ($name === '') {
                throw InvalidCatalogData::required('Name');
            }
            if ($short === '') {
                throw InvalidCatalogData::required('Short name');
            }

            $same = Unit::query()
                ->where(fn ($q) => $q->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->orWhereRaw('LOWER(short_name) = ?', [mb_strtolower($short)]))
                ->when($unit instanceof Unit, fn ($q) => $q->whereKeyNot($unit?->id))
                ->first();

            if ($same instanceof Unit && $unit instanceof Unit) {
                throw InvalidCatalogData::duplicate('unit', $name);
            }
            $unit ??= $same;
            $created = ! $unit instanceof Unit;
            $unit ??= new Unit;

            $unit->fill(['name' => $name, 'short_name' => $short, ...array_intersect_key($data, array_flip(['allows_decimal', 'is_active']))]);
            $unit->save();

            Audit::log($created ? 'unit.created' : 'unit.updated', $unit, ['name' => $name], $actor);

            return $unit;
        });
    }
}
