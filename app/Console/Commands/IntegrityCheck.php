<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Integrity\CheckResult;
use App\Support\Integrity\CheckStatus;
use App\Support\Integrity\IntegrityChecker;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/** Verifies the data invariants per tenant; exits 1 when any check fails (warnings do not). */
final class IntegrityCheck extends Command
{
    protected $signature = 'integrity:check
        {--tenant= : Only this tenant (slug)}
        {--json : Print machine-readable JSON instead of tables}';

    protected $description = 'Check sales, stock, balance, card, numbering and sync invariants for every tenant';

    public function handle(IntegrityChecker $checker, TenantContext $context): int
    {
        $slug = (string) $this->option('tenant');

        /** @var Collection<int, Tenant> $tenants */
        $tenants = $context->withoutTenancy(
            static fn (): Collection => Tenant::query()->when($slug !== '', static fn ($query) => $query->where('slug', $slug))->orderBy('id')->get(),
        );
        if ($tenants->isEmpty()) {
            $this->error($slug === '' ? 'There are no tenants.' : "Tenant [{$slug}] not found.");

            return self::FAILURE;
        }

        $failed = false;
        $report = [];
        foreach ($tenants as $tenant) {
            $results = $checker->run($tenant);
            $tenantFailed = array_any($results, static fn (CheckResult $result): bool => $result->status === CheckStatus::Fail);
            $failed = $failed || $tenantFailed;
            $report[] = [
                'tenant' => $tenant->slug,
                'ok' => ! $tenantFailed,
                'checks' => array_map(static fn (CheckResult $result): array => $result->toArray(), $results),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode(['ok' => ! $failed, 'tenants' => $report], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            foreach ($report as $entry) {
                $this->info("Tenant [{$entry['tenant']}]");
                $this->table(['Check', 'Status', 'Count', 'Sample ids'], array_map(static fn (array $check): array => [
                    $check['name'], $check['status'], $check['count'], implode(', ', $check['sample']),
                ], $entry['checks']));
            }
            $failed ? $this->error('Integrity check failed.') : $this->info('Integrity check passed.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
