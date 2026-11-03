<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Models\Geo\GeoDistrict;
use App\Models\Geo\GeoDivision;
use App\Models\Geo\GeoPostCode;
use App\Models\Geo\GeoUnion;
use App\Models\Geo\GeoUpazila;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the Bangladesh geo reference (05_Schema §3a): cached level listings, cross-level
 * search with the full parent chain, parent resolution / consistency checks for saved customer
 * locations, display names, and the best-effort free-text matcher the legacy import uses.
 * Everything is keyed by `version()`, which changes whenever `geo:import` writes, so the cache
 * never serves stale rows and clients can use it as an ETag.
 *
 * @phpstan-type Place array{id: int, name_en: string, name_bn: string}
 * @phpstan-type Chain array{division: Place|null, district: Place|null, upazila: Place|null, union: Place|null}
 * @phpstan-type Ids array{geo_division_id: int|null, geo_district_id: int|null, geo_upazila_id: int|null, geo_union_id: int|null}
 */
final class GeoCatalog
{
    public const string DIVISIONS = 'divisions';

    public const string DISTRICTS = 'districts';

    public const string UPAZILAS = 'upazilas';

    public const string UNIONS = 'unions';

    public const string POST_CODES = 'post_codes';

    public const array LEVELS = [self::DIVISIONS, self::DISTRICTS, self::UPAZILAS, self::UNIONS, self::POST_CODES];

    private const string VERSION_KEY = 'geo:version';

    private const int SEARCH_LIMIT = 20;

    /** @var array<string, array<int, array<string, mixed>>> per-request memo of the small levels */
    private array $memo = [];

    /** A short fingerprint of the reference data; changes on every import. */
    public function version(): string
    {
        $version = Cache::get(self::VERSION_KEY);
        if (is_string($version) && $version !== '') {
            return $version;
        }
        $parts = [];
        foreach (['geo_divisions', 'geo_districts', 'geo_upazilas', 'geo_unions', 'geo_post_codes'] as $table) {
            $row = DB::table($table)->selectRaw('COUNT(*) AS n, MAX(updated_at) AS u')->first();
            $parts[] = ($row->n ?? 0).':'.($row->u ?? '');
        }
        $version = substr(md5(implode('|', $parts)), 0, 12);
        Cache::put(self::VERSION_KEY, $version, now()->addMinutes(10));

        return $version;
    }

    /** Called after an import: the next read recomputes the version and refills the per-version cache. */
    public function flush(): void
    {
        Cache::forget(self::VERSION_KEY);
        $this->memo = [];
    }

    public static function isLevel(string $level): bool
    {
        return in_array($level, self::LEVELS, true);
    }

    /**
     * The rows of one level under a parent (divisions ignore the parent; post codes take an upazila
     * id, falling back to the upazila's district when the upazila has none of its own).
     *
     * @return list<array<string, mixed>>
     */
    public function children(string $level, ?int $parent): array
    {
        $key = sprintf('geo:%s:%s:%s', $this->version(), $level, $parent ?? 'all');

        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::rememberForever($key, fn (): array => $this->loadChildren($level, $parent));

        return $rows;
    }

    /**
     * Cross-level search (EN, BN or an older romanisation) returning each hit with its parent chain.
     * With `$level` only that level is searched; with `$parent` (a district id for upazilas, an
     * upazila id for unions, a division id for districts) the hits are limited to that parent.
     *
     * @return list<array{level: string, id: int, name_en: string, name_bn: string, type?: string, city_name_en?: string|null, post_code?: string, chain: Chain}>
     */
    public function search(string $q, ?string $level = null, ?int $parent = null, int $limit = self::SEARCH_LIMIT): array
    {
        $q = trim(mb_substr($q, 0, 60));
        if ($q === '') {
            return [];
        }
        $like = '%'.addcslashes($q, '\\%_').'%';
        $prefix = addcslashes($q, '\\%_').'%';
        $levels = $level === null ? [self::UPAZILAS, self::DISTRICTS, self::DIVISIONS, self::UNIONS, self::POST_CODES] : [$level];
        $hits = [];

        foreach ($levels as $current) {
            if (count($hits) >= $limit) {
                break;
            }
            $remaining = $limit - count($hits);
            switch ($current) {
                case self::DIVISIONS:
                    $rows = GeoDivision::query()->where(fn ($w) => $w->where('name_en', 'like', $like)->orWhere('name_bn', 'like', $like)->orWhere('aliases', 'like', $like))
                        ->orderBy('name_en')->limit($remaining)->get();
                    foreach ($rows as $row) {
                        $hits[] = ['level' => self::DIVISIONS, ...$this->place($row->id, $row->name_en, $row->name_bn), 'chain' => $this->chainFor($row->id, null, null, null)];
                    }
                    break;
                case self::DISTRICTS:
                    $rows = GeoDistrict::query()->when($parent !== null, fn ($b) => $b->where('division_id', $parent))
                        ->where(fn ($w) => $w->where('name_en', 'like', $like)->orWhere('name_bn', 'like', $like)->orWhere('aliases', 'like', $like))
                        ->orderBy('name_en')->limit($remaining)->get();
                    foreach ($rows as $row) {
                        $hits[] = ['level' => self::DISTRICTS, ...$this->place($row->id, $row->name_en, $row->name_bn), 'chain' => $this->chainFor($row->division_id, $row->id, null, null)];
                    }
                    break;
                case self::UPAZILAS:
                    $rows = GeoUpazila::query()->when($parent !== null, fn ($b) => $b->where('district_id', $parent))
                        ->where(fn ($w) => $w->where('name_en', 'like', $like)->orWhere('name_bn', 'like', $like)->orWhere('aliases', 'like', $like))
                        ->orderByRaw('CASE WHEN name_en LIKE ? THEN 0 ELSE 1 END', [$prefix])->orderBy('name_en')->limit($remaining)->get();
                    foreach ($rows as $row) {
                        $district = $this->district($row->district_id);
                        $hits[] = ['level' => self::UPAZILAS, ...$this->place($row->id, $row->name_en, $row->name_bn), 'type' => $row->type, 'city_name_en' => $row->city_name_en,
                            'chain' => $this->chainFor($district['division_id'] ?? null, $row->district_id, $row->id, null)];
                    }
                    break;
                case self::UNIONS:
                    $rows = GeoUnion::query()->when($parent !== null, fn ($b) => $b->where('upazila_id', $parent))
                        ->where(fn ($w) => $w->where('name_en', 'like', $like)->orWhere('name_bn', 'like', $like))
                        ->orderBy('name_en')->limit($remaining)->get();
                    foreach ($rows as $row) {
                        $upazila = $this->upazila($row->upazila_id);
                        $district = $upazila === null ? null : $this->district((int) $upazila['district_id']);
                        $hits[] = ['level' => self::UNIONS, ...$this->place($row->id, $row->name_en, $row->name_bn), 'type' => $row->type,
                            'chain' => $this->chainFor($district['division_id'] ?? null, $upazila['district_id'] ?? null, $row->upazila_id, $row->id)];
                    }
                    break;
                case self::POST_CODES:
                    $rows = GeoPostCode::query()->when($parent !== null, fn ($b) => $b->where('upazila_id', $parent))
                        ->where(fn ($w) => $w->where('post_code', 'like', $prefix)->orWhere('post_office_en', 'like', $like))
                        ->orderBy('post_code')->limit($remaining)->get();
                    foreach ($rows as $row) {
                        $district = $this->district($row->district_id);
                        $hits[] = ['level' => self::POST_CODES, 'id' => $row->id, 'name_en' => $row->post_office_en, 'name_bn' => $row->post_office_bn ?? $row->post_office_en, 'post_code' => $row->post_code,
                            'chain' => $this->chainFor($district['division_id'] ?? null, $row->district_id, $row->upazila_id, null)];
                    }
                    break;
            }
        }

        return $hits;
    }

    /**
     * Resolves the full chain from whatever ids are given, filling parents from the deepest child.
     * Returns null when an id is unknown or a child does not belong to the given parent.
     *
     * @return Ids|null
     */
    public function resolve(?int $divisionId, ?int $districtId, ?int $upazilaId, ?int $unionId): ?array
    {
        if ($unionId !== null) {
            $union = GeoUnion::query()->find($unionId);
            if (! $union instanceof GeoUnion || ($upazilaId !== null && $upazilaId !== $union->upazila_id)) {
                return null;
            }
            $upazilaId = $union->upazila_id;
        }
        if ($upazilaId !== null) {
            $upazila = $this->upazila($upazilaId);
            if ($upazila === null || ($districtId !== null && $districtId !== (int) $upazila['district_id'])) {
                return null;
            }
            $districtId = (int) $upazila['district_id'];
        }
        if ($districtId !== null) {
            $district = $this->district($districtId);
            if ($district === null || ($divisionId !== null && $divisionId !== (int) $district['division_id'])) {
                return null;
            }
            $divisionId = (int) $district['division_id'];
        }
        if ($divisionId !== null && $this->division($divisionId) === null) {
            return null;
        }

        return ['geo_division_id' => $divisionId, 'geo_district_id' => $districtId, 'geo_upazila_id' => $upazilaId, 'geo_union_id' => $unionId];
    }

    /**
     * Display names for a saved location (unions are looked up individually; the rest come from the
     * cached small levels).
     *
     * @return Chain
     */
    public function chainFor(?int $divisionId, ?int $districtId, ?int $upazilaId, ?int $unionId): array
    {
        $union = null;
        if ($unionId !== null) {
            $row = GeoUnion::query()->find($unionId);
            $union = $row instanceof GeoUnion ? $this->place($row->id, $row->name_en, $row->name_bn) : null;
        }
        $division = $divisionId === null ? null : $this->division($divisionId);
        $district = $districtId === null ? null : $this->district($districtId);
        $upazila = $upazilaId === null ? null : $this->upazila($upazilaId);

        return [
            'division' => $division === null ? null : $this->place((int) $division['id'], (string) $division['name_en'], (string) $division['name_bn']),
            'district' => $district === null ? null : $this->place((int) $district['id'], (string) $district['name_en'], (string) $district['name_bn']),
            'upazila' => $upazila === null ? null : $this->place((int) $upazila['id'], (string) $upazila['name_en'], (string) $upazila['name_bn']),
            'union' => $union,
        ];
    }

    /** "Upazila, District" (or whatever of the two is known) in the given language. */
    public function label(?int $districtId, ?int $upazilaId, string $locale = 'en'): ?string
    {
        $column = $locale === 'bn' ? 'name_bn' : 'name_en';
        $parts = [];
        $upazila = $upazilaId === null ? null : $this->upazila($upazilaId);
        if ($upazila !== null) {
            $parts[] = (string) $upazila[$column];
        }
        $district = $districtId === null ? null : $this->district($districtId);
        if ($district !== null && ($upazila === null || $upazila[$column] !== $district[$column])) {
            $parts[] = (string) $district[$column];
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Best-effort match of a free-text address (legacy import): the longest upazila / metro thana
     * name (EN, BN or alias) found as a word in the text, then a district name. An upazila whose name
     * exists in several districts counts only when the text names its district too.
     *
     * @return Ids|null
     */
    public function matchAddress(?string $text): ?array
    {
        $haystack = ' '.mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $text) ?? '').' ';
        if (trim($haystack) === '') {
            return null;
        }

        $districtId = null;
        foreach ($this->all(self::DISTRICTS) as $district) {
            if ($this->mentions($haystack, $district)) {
                $districtId = (int) $district['id'];
                break;
            }
        }

        $candidates = [];
        foreach ($this->all(self::UPAZILAS) as $upazila) {
            if ($this->mentions($haystack, $upazila)) {
                $candidates[] = $upazila;
            }
        }
        if ($districtId !== null) {
            $candidates = array_values(array_filter($candidates, fn (array $u): bool => (int) $u['district_id'] === $districtId));
        }
        usort($candidates, fn (array $a, array $b): int => mb_strlen((string) $b['name_en']) <=> mb_strlen((string) $a['name_en']));
        $upazila = $candidates[0] ?? null;
        if ($upazila !== null && $districtId === null) {
            $sameName = array_filter($this->all(self::UPAZILAS), fn (array $u): bool => mb_strtolower((string) $u['name_en']) === mb_strtolower((string) $upazila['name_en']));
            if (count($sameName) > 1) {
                $upazila = null;
            }
        }
        if ($upazila === null && $districtId === null) {
            return null;
        }

        return $this->resolve(null, $districtId, $upazila === null ? null : (int) $upazila['id'], null);
    }

    /** @param array<string, mixed> $row */
    private function mentions(string $haystack, array $row): bool
    {
        $names = [(string) $row['name_en'], (string) $row['name_bn']];
        foreach (explode(',', (string) ($row['aliases'] ?? '')) as $alias) {
            if (trim($alias) !== '') {
                $names[] = trim($alias);
            }
        }
        foreach ($names as $name) {
            $needle = ' '.mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? '')).' ';
            if (mb_strlen(trim($needle)) >= 3 && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed>|null */
    private function division(int $id): ?array
    {
        return $this->all(self::DIVISIONS)[$id] ?? null;
    }

    /** @return array<string, mixed>|null */
    private function district(int $id): ?array
    {
        return $this->all(self::DISTRICTS)[$id] ?? null;
    }

    /** @return array<string, mixed>|null */
    private function upazila(int $id): ?array
    {
        return $this->all(self::UPAZILAS)[$id] ?? null;
    }

    /**
     * Every row of a small level keyed by id (divisions, districts, upazilas), cached per version.
     *
     * @return array<int, array<string, mixed>>
     */
    private function all(string $level): array
    {
        if (isset($this->memo[$level])) {
            return $this->memo[$level];
        }
        $key = sprintf('geo:%s:all:%s', $this->version(), $level);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = Cache::rememberForever($key, function () use ($level): array {
            $query = match ($level) {
                self::DIVISIONS => GeoDivision::query()->orderBy('name_en'),
                self::DISTRICTS => GeoDistrict::query()->orderBy('name_en'),
                default => GeoUpazila::query()->orderBy('name_en'),
            };
            $out = [];
            foreach ($query->get() as $row) {
                /** @var array<string, mixed> $attributes */
                $attributes = $row->attributesToArray();
                $out[(int) $row->getKey()] = $attributes;
            }

            return $out;
        });

        return $this->memo[$level] = $rows;
    }

    /** @return list<array<string, mixed>> */
    private function loadChildren(string $level, ?int $parent): array
    {
        $place = fn (int $id, string $en, string $bn): array => $this->place($id, $en, $bn);

        return match ($level) {
            self::DIVISIONS => array_values(array_map(fn (array $r): array => $place((int) $r['id'], (string) $r['name_en'], (string) $r['name_bn']), $this->all(self::DIVISIONS))),
            self::DISTRICTS => array_values(array_map(
                fn (array $r): array => [...$place((int) $r['id'], (string) $r['name_en'], (string) $r['name_bn']), 'division_id' => (int) $r['division_id']],
                array_filter($this->all(self::DISTRICTS), fn (array $r): bool => $parent === null || (int) $r['division_id'] === $parent),
            )),
            self::UPAZILAS => array_values(array_map(
                fn (array $r): array => [...$place((int) $r['id'], (string) $r['name_en'], (string) $r['name_bn']), 'district_id' => (int) $r['district_id'], 'type' => (string) $r['type'], 'city_name_en' => $r['city_name_en']],
                array_filter($this->all(self::UPAZILAS), fn (array $r): bool => $parent === null || (int) $r['district_id'] === $parent),
            )),
            self::UNIONS => array_values(GeoUnion::query()->when($parent !== null, fn ($q) => $q->where('upazila_id', $parent))->orderBy('name_en')->get()
                ->map(fn (GeoUnion $u): array => [...$place($u->id, $u->name_en, $u->name_bn), 'upazila_id' => $u->upazila_id, 'type' => $u->type])->all()),
            self::POST_CODES => $this->loadPostCodes($parent),
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private function loadPostCodes(?int $upazilaId): array
    {
        $query = GeoPostCode::query()->orderBy('post_code')->orderBy('post_office_en');
        if ($upazilaId !== null) {
            $own = (clone $query)->where('upazila_id', $upazilaId)->get();
            if ($own->isEmpty()) {
                $upazila = $this->upazila($upazilaId);
                $own = $upazila === null ? $own : (clone $query)->where('district_id', (int) $upazila['district_id'])->get();
            }
            $rows = $own;
        } else {
            $rows = $query->limit(2000)->get();
        }

        return array_values($rows->map(fn (GeoPostCode $p): array => [
            'id' => $p->id, 'post_code' => $p->post_code, 'post_office_en' => $p->post_office_en, 'post_office_bn' => $p->post_office_bn,
            'district_id' => $p->district_id, 'upazila_id' => $p->upazila_id,
        ])->all());
    }

    /** @return Place */
    private function place(int $id, string $en, string $bn): array
    {
        return ['id' => $id, 'name_en' => $en, 'name_bn' => $bn];
    }
}
