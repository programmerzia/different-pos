<?php

declare(strict_types=1);

namespace App\Legacy;

use App\Legacy\Steps\BrandsAndCategories;
use App\Legacy\Steps\CustomerGroupsAndCustomers;
use App\Legacy\Steps\Payments;
use App\Legacy\Steps\Products;
use App\Legacy\Steps\Recompute;
use App\Legacy\Steps\ReconcileStock;
use App\Legacy\Steps\Returns;
use App\Legacy\Steps\Roles;
use App\Legacy\Steps\Sales;
use App\Legacy\Steps\Step;
use App\Legacy\Steps\StockInvoicesToPurchasesAndBatches;
use App\Legacy\Steps\StoresAndBranches;
use App\Legacy\Steps\Users;
use App\Legacy\Steps\Vendors;
use App\Support\Database\Transaction;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;
use Spatie\Activitylog\Support\ActivityLogger;

/**
 * Imports legacy stores into one tenant (05_Schema §12, ADR 0002). The whole run is ONE transaction:
 * a failure or --dry-run leaves nothing behind. Idempotent through legacy_id_maps, so a second run
 * creates nothing new. The per-row activity log is switched off (one summary entry is written instead).
 */
final readonly class Importer
{
    /** @var list<class-string<Step>> after StoresAndBranches, in mapping order */
    public const array STEPS = [
        Roles::class,
        Users::class,
        BrandsAndCategories::class,
        Vendors::class,
        CustomerGroupsAndCustomers::class,
        Products::class,
        StockInvoicesToPurchasesAndBatches::class,
        ReconcileStock::class,
        Sales::class,
        Payments::class,
        Returns::class,
        Recompute::class,
    ];

    public function __construct(
        private Container $container,
        private TenantContext $tenancy,
        private ActivityLogger $activity,
        private Reconciliation $reconciliation,
    ) {}

    /** @throws DryRunRollback after a successful dry run (everything rolled back) */
    public function run(ImportOptions $options): ImportResult
    {
        $started = hrtime(true);
        $context = new ImportContext($options);

        return $this->activity->withoutLogging(fn (): ImportResult => Transaction::run(function () use ($context, $started): ImportResult {
            $this->step($context, $this->container->make(StoresAndBranches::class));

            return $this->tenancy->run($context->tenant(), null, function () use ($context, $started): ImportResult {
                foreach (self::STEPS as $class) {
                    $this->step($context, $this->container->make($class));
                }

                $result = $this->finish($context, $started);
                if ($context->options->dryRun) {
                    throw new DryRunRollback($result);
                }

                return $result;
            });
        }, attempts: 1));
    }

    private function step(ImportContext $context, Step $step): void
    {
        $started = hrtime(true);
        $context->beginStep($step->name());
        $step->run($context);
        $context->ids->flush();
        $context->endStep((int) ((hrtime(true) - $started) / 1_000_000));
    }

    private function finish(ImportContext $context, int|float $started): ImportResult
    {
        $reconciliation = $this->reconciliation->build($context);
        $directory = rtrim($context->options->reportDir, '/');
        $reportPath = $directory.'/migration_report.csv';
        $reconciliationPath = $directory.'/reconciliation.csv';

        Csv::write($reportPath, ['kind', 'legacy_table', 'legacy_id', 'message'], $context->report->lines());
        Csv::write($reconciliationPath, ['store', 'entity', 'legacy_count', 'new_count', 'legacy_sum', 'new_sum', 'diff'], $reconciliation);

        $result = new ImportResult(
            tenantId: $context->tenantId(),
            dryRun: $context->options->dryRun,
            steps: $context->stats(),
            reportCounts: $context->report->countsByKind(),
            reconciliation: $reconciliation,
            reportPath: $reportPath,
            reconciliationPath: $reconciliationPath,
            seconds: round((hrtime(true) - $started) / 1e9, 1),
        );

        if (! $context->options->dryRun) {
            $this->activity->enableLogging();
            activity('legacy')->event('legacy_import')->withProperties([
                'stores' => $context->stores(),
                'steps' => $result->steps,
                'report' => $result->reportCounts,
            ])->log('legacy_import');
            $this->activity->disableLogging();
        }

        return $result;
    }
}
