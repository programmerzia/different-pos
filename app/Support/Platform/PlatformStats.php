<?php

declare(strict_types=1);

namespace App\Support\Platform;

use App\Models\Backup;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** The platform console's numbers. Every value is measured; nothing here is estimated or hard-coded. */
final readonly class PlatformStats
{
    public function __construct(private TenantContext $context) {}

    /** @return array{tenants: int, active_tenants: int, suspended_tenants: int, active_users: int, gmv_30d_paisa: int, sales_30d: int, storage_bytes: int} */
    public function kpis(): array
    {
        return $this->context->withoutTenancy(function (): array {
            $sales = DB::table('sales')
                ->where('status', 'completed')
                ->where('sold_at', '>=', now()->subDays(30))
                ->selectRaw('COUNT(*) AS n, COALESCE(SUM(total - returned_total), 0) AS gmv')
                ->first();

            return [
                'tenants' => Tenant::query()->count(),
                'active_tenants' => Tenant::query()->where('status', Tenant::STATUS_ACTIVE)->count(),
                'suspended_tenants' => Tenant::query()->where('status', Tenant::STATUS_SUSPENDED)->count(),
                'active_users' => DB::table('users')->whereNotNull('tenant_id')->whereNull('deleted_at')->where('is_active', true)->count(),
                'gmv_30d_paisa' => (int) ($sales->gmv ?? 0),
                'sales_30d' => (int) ($sales->n ?? 0),
                'storage_bytes' => $this->storageBytes(),
            ];
        });
    }

    /** @return list<array{id: int, name: string, slug: string, status: string, phone: string|null, email: string|null, timezone: string, branches: int, users: int, last_activity_at: string|null, created_at: string|null}> */
    public function tenants(): array
    {
        return $this->context->withoutTenancy(function (): array {
            $rows = Tenant::query()
                ->select('tenants.*')
                ->selectSub(DB::table('branches')->selectRaw('COUNT(*)')->whereColumn('branches.tenant_id', 'tenants.id')->whereNull('branches.deleted_at'), 'branches_count')
                ->selectSub(DB::table('users')->selectRaw('COUNT(*)')->whereColumn('users.tenant_id', 'tenants.id')->whereNull('users.deleted_at')->where('users.is_active', true), 'users_count')
                ->selectSub(DB::table('activity_log')->selectRaw('MAX(created_at)')->whereColumn('activity_log.tenant_id', 'tenants.id'), 'last_activity_at')
                ->selectSub(DB::table('users')->selectRaw('MAX(last_login_at)')->whereColumn('users.tenant_id', 'tenants.id'), 'last_login_at')
                ->orderBy('tenants.name')
                ->get();

            return array_values($rows->map(static function (Tenant $t): array {
                $activity = $t->getAttribute('last_activity_at');
                $login = $t->getAttribute('last_login_at');
                $last = collect([$activity, $login])->filter(static fn (mixed $v): bool => is_string($v) && $v !== '')->max();

                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'status' => $t->status,
                    'phone' => $t->phone,
                    'email' => $t->email,
                    'timezone' => $t->timezone,
                    'branches' => (int) $t->getAttribute('branches_count'),
                    'users' => (int) $t->getAttribute('users_count'),
                    'last_activity_at' => is_string($last) ? Carbon::parse($last)->toIso8601String() : null,
                    'created_at' => $t->created_at?->toIso8601String(),
                ];
            })->all());
        });
    }

    /**
     * @return array{
     *   database: array{ok: bool, driver: string, version: string|null, error: string|null},
     *   queue: array{driver: string, pending: int|null, failed: int|null},
     *   cache: array{driver: string},
     *   backup: array{last_completed_at: string|null, last_status: string|null, disks: list<string>, encrypted: bool},
     *   disk: array{free_bytes: int|null, total_bytes: int|null}
     * }
     */
    public function health(): array
    {
        $dbDriver = (string) config('database.default');
        try {
            $version = DB::connection()->selectOne('SELECT VERSION() AS v');
            $database = ['ok' => true, 'driver' => $dbDriver, 'version' => is_object($version) && isset($version->v) ? (string) $version->v : null, 'error' => null];
        } catch (Throwable $e) {
            $database = ['ok' => false, 'driver' => $dbDriver, 'version' => null, 'error' => mb_substr($e->getMessage(), 0, 200)];
        }

        $queueDriver = (string) config('queue.default');
        $pending = $failed = null;
        if ($database['ok']) {
            try {
                if ($queueDriver === 'database') {
                    $pending = DB::table((string) config('queue.connections.database.table', 'jobs'))->count();
                }
                $failed = DB::table((string) config('queue.failed.table', 'failed_jobs'))->count();
            } catch (Throwable) {
                // Tables missing on a fresh install: counts stay unknown rather than fabricated.
            }
        }

        $lastBackup = Backup::query()->where('status', Backup::STATUS_COMPLETED)->orderByDesc('finished_at')->first();
        $lastAny = Backup::query()->orderByDesc('id')->first();
        /** @var list<string> $disks */
        $disks = array_values((array) config('backup.backup.destination.disks', []));

        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        return [
            'database' => $database,
            'queue' => ['driver' => $queueDriver, 'pending' => $pending, 'failed' => $failed],
            'cache' => ['driver' => (string) config('cache.default')],
            'backup' => [
                'last_completed_at' => $lastBackup?->finished_at?->toIso8601String(),
                'last_status' => $lastAny?->status,
                'disks' => $disks,
                'encrypted' => filled(config('backup.backup.password')),
            ],
            'disk' => ['free_bytes' => $free === false ? null : (int) $free, 'total_bytes' => $total === false ? null : (int) $total],
        ];
    }

    /** Bytes under storage/app/public (tenant logos, product photos, card exports); cached 10 minutes. */
    private function storageBytes(): int
    {
        return (int) Cache::remember('platform.storage_bytes', now()->addMinutes(10), static function (): int {
            $disk = Storage::disk('public');
            $bytes = 0;
            foreach ($disk->allFiles() as $file) {
                $bytes += (int) $disk->size($file);
            }

            return $bytes;
        });
    }
}
