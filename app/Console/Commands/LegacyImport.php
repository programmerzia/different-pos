<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Legacy\DryRunRollback;
use App\Legacy\Importer;
use App\Legacy\ImportOptions;
use App\Legacy\ImportResult;
use Illuminate\Console\Command;

/** Thin wrapper around App\Legacy\Importer (05_Schema §12, ADR 0002). */
final class LegacyImport extends Command
{
    protected $signature = 'legacy:import
        {--connection=legacy : Read-only database connection of the legacy POS}
        {--stores= : Comma-separated legacy store ids, e.g. 7,10}
        {--tenant= : Target tenant slug (created if missing)}
        {--dry-run : Run everything, write the reports, then roll back}
        {--report-dir= : Where migration_report.csv and reconciliation.csv go (default storage/app/migration)}';

    protected $description = 'Import legacy POS stores into one tenant (idempotent; --dry-run rolls back)';

    public function handle(Importer $importer): int
    {
        $stores = array_values(array_filter(array_map(intval(...), explode(',', (string) $this->option('stores')))));
        $tenant = (string) $this->option('tenant');
        if ($stores === [] || $tenant === '') {
            $this->error('Both --stores and --tenant are required.');

            return self::INVALID;
        }

        $options = new ImportOptions(
            connection: (string) $this->option('connection'),
            stores: $stores,
            tenantSlug: $tenant,
            dryRun: (bool) $this->option('dry-run'),
            reportDir: (string) ($this->option('report-dir') ?: config('legacy.report_dir')),
            chunk: (int) config('legacy.chunk', 500),
        );

        $this->info(sprintf('Importing legacy stores %s into [%s]%s...', implode(', ', $stores), $tenant, $options->dryRun ? ' (dry run)' : ''));

        try {
            $result = $importer->run($options);
        } catch (DryRunRollback $rollback) {
            $result = $rollback->result;
        }

        $this->summary($result);

        return self::SUCCESS;
    }

    private function summary(ImportResult $result): void
    {
        $this->table(['Step', 'Written', 'Skipped', 'ms'], array_map(
            static fn (string $step, array $stats): array => [$step, $stats['created'], $stats['skipped'], $stats['ms']],
            array_keys($result->steps),
            $result->steps,
        ));

        $this->table(['Report kind', 'Lines'], array_map(
            static fn (string $kind, int $count): array => [$kind, $count],
            array_keys($result->reportCounts),
            $result->reportCounts,
        ));

        $differences = array_values(array_filter($result->reconciliation, static fn (array $row): bool => ! in_array((string) $row['diff'], ['0', '0.00'], true)));
        $this->table(['Store', 'Entity', 'Legacy', 'New', 'Diff'], array_map(static fn (array $row): array => [
            $row['store'], $row['entity'],
            $row['legacy_sum'] !== '' ? $row['legacy_sum'] : $row['legacy_count'],
            $row['new_sum'] !== '' ? $row['new_sum'] : $row['new_count'],
            $row['diff'],
        ], $differences));
        if ($differences === []) {
            $this->info('Reconciliation: no differences.');
        }

        $this->line('Reports: '.$result->reportPath.', '.$result->reconciliationPath);
        $this->info(sprintf('%s in %.1f s (tenant id %d).', $result->dryRun ? 'Dry run finished and rolled back' : 'Import committed', $result->seconds, $result->tenantId));
    }
}
