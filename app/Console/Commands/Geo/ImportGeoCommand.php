<?php

declare(strict_types=1);

namespace App\Console\Commands\Geo;

use App\Support\Geo\GeoCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * `geo:import`: loads database/data/geo/*.json (divisions, districts, upazilas incl. metro thanas,
 * unions, post codes; see docs/adr/0008-geo-reference-data.md) into the global geo_* tables. Idempotent:
 * rows are upserted by `code` (post codes by district + office + code) and the data keeps its stable ids,
 * so a re-run after a data update changes only what changed. --path points at another folder (tests
 * use a small fixture set). Not a business write: reference data, no tenant, no sync_version.
 */
final class ImportGeoCommand extends Command
{
    protected $signature = 'geo:import {--path= : Folder with the five JSON files (default database/data/geo)}';

    protected $description = 'Import the Bangladesh geo reference data (divisions, districts, upazilas, unions, post codes)';

    public function handle(GeoCatalog $catalog): int
    {
        $path = (string) ($this->option('path') ?? '');
        $dir = $path === '' ? database_path('data/geo') : rtrim($path, '/');
        if (! is_dir($dir)) {
            $this->error("Geo data folder not found: {$dir}");

            return self::FAILURE;
        }

        try {
            $divisions = $this->read($dir, 'divisions');
            $districts = $this->read($dir, 'districts');
            $upazilas = $this->read($dir, 'upazilas');
            $unions = $this->read($dir, 'unions');
            $postCodes = $this->read($dir, 'post_codes');
        } catch (JsonException $e) {
            $this->error('Invalid geo data: '.$e->getMessage());

            return self::FAILURE;
        }

        $counts = DB::transaction(function () use ($divisions, $districts, $upazilas, $unions, $postCodes): array {
            $now = now();
            $this->upsert('geo_divisions', $divisions, fn (array $r): array => [
                'id' => (int) $r['id'], 'code' => (string) $r['code'], 'name_en' => (string) $r['name_en'], 'name_bn' => (string) $r['name_bn'], 'aliases' => $this->aliases($r),
            ], ['code'], ['name_en', 'name_bn', 'aliases', 'updated_at'], $now);
            $divisionIds = $this->idsByCode('geo_divisions');

            $this->upsert('geo_districts', $districts, fn (array $r): array => [
                'id' => (int) $r['id'], 'division_id' => $divisionIds[(string) $r['division_code']] ?? throw new JsonException("Unknown division code {$r['division_code']}"),
                'code' => (string) $r['code'], 'name_en' => (string) $r['name_en'], 'name_bn' => (string) $r['name_bn'], 'aliases' => $this->aliases($r),
            ], ['code'], ['division_id', 'name_en', 'name_bn', 'aliases', 'updated_at'], $now);
            $districtIds = $this->idsByCode('geo_districts');

            $this->upsert('geo_upazilas', $upazilas, fn (array $r): array => [
                'id' => (int) $r['id'], 'district_id' => $districtIds[(string) $r['district_code']] ?? throw new JsonException("Unknown district code {$r['district_code']}"),
                'code' => (string) $r['code'], 'type' => (string) ($r['type'] ?? 'upazila'), 'name_en' => (string) $r['name_en'], 'name_bn' => (string) $r['name_bn'],
                'city_name_en' => isset($r['city_name_en']) ? (string) $r['city_name_en'] : null, 'aliases' => $this->aliases($r),
            ], ['code'], ['district_id', 'type', 'name_en', 'name_bn', 'city_name_en', 'aliases', 'updated_at'], $now);
            $upazilaIds = $this->idsByCode('geo_upazilas');

            $this->upsert('geo_unions', $unions, fn (array $r): array => [
                'id' => (int) $r['id'], 'upazila_id' => $upazilaIds[(string) $r['upazila_code']] ?? throw new JsonException("Unknown upazila code {$r['upazila_code']}"),
                'code' => (string) $r['code'], 'type' => (string) ($r['type'] ?? 'union'), 'name_en' => (string) $r['name_en'], 'name_bn' => (string) $r['name_bn'],
            ], ['code'], ['upazila_id', 'type', 'name_en', 'name_bn', 'updated_at'], $now);

            $this->upsert('geo_post_codes', $postCodes, fn (array $r): array => [
                'district_id' => $districtIds[(string) $r['district_code']] ?? throw new JsonException("Unknown district code {$r['district_code']}"),
                'upazila_id' => isset($r['upazila_code']) ? ($upazilaIds[(string) $r['upazila_code']] ?? null) : null,
                'post_office_en' => (string) $r['post_office_en'], 'post_office_bn' => isset($r['post_office_bn']) ? (string) $r['post_office_bn'] : null, 'post_code' => (string) $r['post_code'],
            ], ['district_id', 'post_office_en', 'post_code'], ['upazila_id', 'post_office_bn', 'updated_at'], $now);

            return [
                'divisions' => DB::table('geo_divisions')->count(),
                'districts' => DB::table('geo_districts')->count(),
                'upazilas' => DB::table('geo_upazilas')->count(),
                'unions' => DB::table('geo_unions')->count(),
                'post_codes' => DB::table('geo_post_codes')->count(),
            ];
        });

        $catalog->flush();
        foreach ($counts as $table => $count) {
            $this->line(sprintf('%-11s %6d', $table, $count));
        }
        $this->info('Geo reference data imported.');

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    private function read(string $dir, string $name): array
    {
        $file = "{$dir}/{$name}.json";
        $raw = is_file($file) ? file_get_contents($file) : false;
        if ($raw === false) {
            throw new JsonException("{$name}.json is missing");
        }
        $rows = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new JsonException("{$name}.json must be a JSON list");
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new JsonException("{$name}.json has a non-object row");
            }
        }

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array<string, mixed>  $map
     * @param  non-empty-list<non-empty-string>  $by
     * @param  list<string>  $update
     */
    private function upsert(string $table, array $rows, callable $map, array $by, array $update, mixed $now): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            $values = array_map(fn (array $r): array => [...$map($r), 'created_at' => $now, 'updated_at' => $now], $chunk);
            DB::table($table)->upsert($values, $by, $update);
        }
    }

    /** @return array<string, int> */
    private function idsByCode(string $table): array
    {
        return DB::table($table)->pluck('id', 'code')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /** @param array<string, mixed> $row */
    private function aliases(array $row): ?string
    {
        $aliases = $row['aliases'] ?? null;
        if (! is_array($aliases) || $aliases === []) {
            return null;
        }

        return implode(',', array_map(fn (mixed $a): string => trim((string) $a), $aliases));
    }
}
