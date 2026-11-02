<?php

declare(strict_types=1);

namespace App\Legacy;

use App\Legacy\Transform\Text;
use App\Models\Branch;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Shared state of one import run: options, the target tenant and branches, the id map, the
 * report, per-step counters and a few per-tenant lookups that several steps need.
 */
final class ImportContext
{
    public readonly IdMap $ids;

    public readonly MigrationReport $report;

    public readonly CarbonImmutable $startedAt;

    private ?Tenant $tenant = null;

    /** @var array<int, Branch> legacy store id => branch */
    private array $branches = [];

    /** @var array<int, int> branch id => register id */
    private array $registers = [];

    /** @var array<string, int> */
    private array $lookups = [];

    /** @var array<string, array{created: int, skipped: int, ms: int}> */
    private array $stats = [];

    private string $step = '';

    public function __construct(public readonly ImportOptions $options)
    {
        $this->ids = new IdMap;
        $this->report = new MigrationReport;
        $this->startedAt = CarbonImmutable::now();
    }

    public function legacy(): Connection
    {
        return DB::connection($this->options->connection);
    }

    /**
     * Explicit column lists only (never SELECT *). Text is read as raw bytes and money as an exact
     * decimal string, so neither passes through a PHP float or a latin1 conversion.
     *
     * @param  list<literal-string>  $plain
     * @param  list<literal-string>  $text
     * @param  list<literal-string>  $money
     */
    public function select(string $table, array $plain, array $text = [], array $money = []): Builder
    {
        $columns = $plain;
        foreach ($text as $column) {
            $columns[] = new Expression("CAST(`{$column}` AS BINARY) AS `{$column}`");
        }
        foreach ($money as $column) {
            $columns[] = new Expression("CAST(`{$column}` AS DECIMAL(20,4)) AS `{$column}`");
        }

        return $this->legacy()->table($table)->select($columns);
    }

    /** Decodes a BINARY text column, reporting a Windows-1252 fallback. */
    public function text(mixed $bytes, string $table, int $legacyId, string $column): ?string
    {
        [$value, $fallback] = Text::decode($bytes);
        if ($fallback) {
            $this->report->add('encoding_fallback', $table, $legacyId, "{$column} was not valid UTF-8; decoded as Windows-1252");
        }

        return $value;
    }

    /** @return list<int> */
    public function stores(): array
    {
        return $this->options->stores;
    }

    public function setTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new LogicException('The import tenant is not set yet.');
    }

    public function tenantId(): int
    {
        return $this->tenant()->id;
    }

    public function setBranch(int $store, Branch $branch, int $registerId): void
    {
        $this->branches[$store] = $branch;
        $this->registers[$branch->id] = $registerId;
    }

    public function branch(int $store): Branch
    {
        return $this->branches[$store] ?? throw new LogicException("Legacy store {$store} has no branch.");
    }

    public function branchId(int $store): int
    {
        return $this->branch($store)->id;
    }

    public function registerId(int $branchId): int
    {
        return $this->registers[$branchId] ?? throw new LogicException("Branch {$branchId} has no register.");
    }

    public function setLookup(string $key, int $id): void
    {
        $this->lookups[$key] = $id;
    }

    public function lookup(string $key): int
    {
        return $this->lookups[$key] ?? throw new LogicException("Import lookup [{$key}] is not set.");
    }

    public function hasLookup(string $key): bool
    {
        return isset($this->lookups[$key]);
    }

    public function beginStep(string $name): void
    {
        $this->step = $name;
        $this->stats[$name] ??= ['created' => 0, 'skipped' => 0, 'ms' => 0];
    }

    public function endStep(int $ms): void
    {
        $this->stats[$this->step]['ms'] = $ms;
    }

    public function created(int $count = 1): void
    {
        $this->stats[$this->step]['created'] += $count;
    }

    public function skipped(int $count = 1): void
    {
        $this->stats[$this->step]['skipped'] += $count;
    }

    /** @return array<string, array{created: int, skipped: int, ms: int}> */
    public function stats(): array
    {
        return $this->stats;
    }

    /** Wall-clock "now" for row timestamps (one value per run). */
    public function now(): string
    {
        return $this->startedAt->utc()->format('Y-m-d H:i:s');
    }
}
