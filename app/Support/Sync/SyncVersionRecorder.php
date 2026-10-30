<?php

declare(strict_types=1);

namespace App\Support\Sync;

use App\Support\Database\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Collects Syncable rows written during a Transaction::run() and gives them sync_version values in
 * ONE tenants.sync_counter lock taken just before commit (04_TRD §3.5 locking order, ADR 0001 D6).
 *
 * Because that row lock is held until commit, versions become visible in commit order, so a pull
 * cursor (sync_version > cursor) can never skip a row.
 */
final class SyncVersionRecorder
{
    /** @var array<string, Model> keyed by "table:id"; last write wins */
    private array $pending = [];

    private int $depth = 0;

    public function record(Model $model): void
    {
        if ($this->depth === 0) {
            // Written outside Transaction::run() (factories, seeders, tinker): allocate right away in a
            // short (possibly nested) transaction. Business Actions always go through Transaction::run(),
            // where allocation is deferred to just before commit.
            Transaction::run(function () use ($model): void {
                $this->pending[$model->getTable().':'.$model->getKey()] = $model;
            });

            return;
        }

        $this->pending[$model->getTable().':'.$model->getKey()] = $model;
    }

    /** @internal Transaction::run() */
    public function enter(): void
    {
        if ($this->depth === 0) {
            $this->pending = [];
        }
        $this->depth++;
    }

    /** @internal Transaction::run() */
    public function leave(): void
    {
        $this->depth = max(0, $this->depth - 1);
        if ($this->depth === 0) {
            $this->pending = [];
        }
    }

    /** @internal Transaction::run(); called at the end of the outermost callback, inside the transaction. */
    public function isOutermost(): bool
    {
        return $this->depth === 1;
    }

    /** @internal Transaction::run() retries a deadlocked attempt from scratch. */
    public function resetAttempt(): void
    {
        $this->pending = [];
    }

    /** @internal Assigns versions for everything recorded in this transaction. */
    public function flush(): void
    {
        if ($this->pending === []) {
            return;
        }

        /** @var array<int, list<Model>> $byTenant */
        $byTenant = [];
        foreach ($this->pending as $model) {
            $tenantId = $model->getAttribute('tenant_id');
            if (! is_int($tenantId)) {
                throw new LogicException(sprintf('Syncable %s has no integer tenant_id.', $model::class));
            }
            $byTenant[$tenantId][] = $model;
        }
        ksort($byTenant); // deterministic lock order across tenants

        foreach ($byTenant as $tenantId => $models) {
            $counter = DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->value('sync_counter');
            if (! is_int($counter)) {
                throw new LogicException('Tenant '.$tenantId.' not found while assigning sync versions.');
            }

            DB::table('tenants')->where('id', $tenantId)->update(['sync_counter' => $counter + count($models)]);

            foreach ($models as $offset => $model) {
                $version = $counter + $offset + 1;
                DB::table($model->getTable())
                    ->where($model->getKeyName(), $model->getKey())
                    ->where('tenant_id', $tenantId)
                    ->update(['sync_version' => $version]);
                $model->setAttribute('sync_version', $version);
                $model->syncOriginalAttribute('sync_version');
            }
        }

        $this->pending = [];
    }
}
