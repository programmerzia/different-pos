<?php

declare(strict_types=1);

namespace App\Support\Integrity;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * One invariant. Subclasses build a query that returns the offending rows as a single `ref`
 * column, already filtered to the tenant, and the base class counts them and takes a sample.
 */
abstract class Check
{
    public const int SAMPLE = 10;

    /** Short snake_case name shown in the report. */
    abstract public function name(): string;

    abstract public function run(int $tenantId): CheckResult;

    /**
     * @param  string  $offenders  SELECT ... AS ref
     * @return array{int, list<int|string>}
     */
    protected function scan(string $offenders): array
    {
        $count = (int) DB::scalar("SELECT COUNT(*) FROM ({$offenders}) AS offenders");
        if ($count === 0) {
            return [0, []];
        }

        $sample = array_values(array_map(
            static fn (stdClass $row): int|string => is_numeric($row->ref) && (string) (int) $row->ref === (string) $row->ref ? (int) $row->ref : (string) $row->ref,
            DB::select("SELECT ref FROM ({$offenders}) AS offenders ORDER BY ref LIMIT ".self::SAMPLE),
        ));

        return [$count, $sample];
    }

    /** Fails when the query returns rows. */
    protected function failing(string $offenders): CheckResult
    {
        [$count, $sample] = $this->scan($offenders);

        return new CheckResult($this->name(), $count === 0 ? CheckStatus::Ok : CheckStatus::Fail, $count, $sample);
    }

    /** @param  list<int|string>  $sample */
    protected function result(CheckStatus $status, int $count, array $sample = []): CheckResult
    {
        return new CheckResult($this->name(), $status, $count, array_slice($sample, 0, self::SAMPLE));
    }
}
