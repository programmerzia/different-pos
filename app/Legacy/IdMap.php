<?php

declare(strict_types=1);

namespace App\Legacy;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * legacy_id_maps, cached per entity. Legacy ids are unique per legacy table across stores, so a
 * lookup needs only (entity, legacy id). New entries are buffered and bulk-inserted by flush().
 */
final class IdMap
{
    /** @var array<string, array<int, int>> entity => legacy id => new id */
    private array $cache = [];

    /** @var list<array{entity: string, legacy_store_id: int, legacy_id: int, new_id: int}> */
    private array $pending = [];

    public function get(string $entity, int $legacyId): ?int
    {
        return $this->all($entity)[$legacyId] ?? null;
    }

    public function has(string $entity, int $legacyId): bool
    {
        return $this->get($entity, $legacyId) !== null;
    }

    /** @return array<int, int> */
    public function all(string $entity): array
    {
        if (! array_key_exists($entity, $this->cache)) {
            $this->cache[$entity] = [];
            DB::table('legacy_id_maps')->where('entity', $entity)->orderBy('id')
                ->get(['legacy_id', 'new_id'])
                ->each(function (stdClass $row) use ($entity): void {
                    $this->cache[$entity][(int) $row->legacy_id] = (int) $row->new_id;
                });
        }

        return $this->cache[$entity];
    }

    public function put(string $entity, int $store, int $legacyId, int $newId): void
    {
        $this->all($entity);
        $this->cache[$entity][$legacyId] = $newId;
        $this->pending[] = ['entity' => $entity, 'legacy_store_id' => $store, 'legacy_id' => $legacyId, 'new_id' => $newId];

        if (count($this->pending) >= 1000) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        foreach (array_chunk($this->pending, 1000) as $chunk) {
            DB::table('legacy_id_maps')->insert($chunk);
        }
        $this->pending = [];
    }
}
