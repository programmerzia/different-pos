<?php

declare(strict_types=1);

namespace App\Support\Database;

use App\Support\Sync\SyncVersionRecorder;
use Illuminate\Support\Facades\DB;

/**
 * The only way business Actions open a transaction (CLAUDE.md, 04_TRD §3.1).
 * Wraps DB::transaction() and, at the end of the OUTERMOST callback, assigns sync versions
 * (the tenants.sync_counter lock is the last lock taken: 04_TRD §3.5).
 */
final class Transaction
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  int<1, max>  $attempts
     * @return T
     */
    public static function run(callable $callback, int $attempts = 3): mixed
    {
        $recorder = app(SyncVersionRecorder::class);
        $recorder->enter();

        try {
            return DB::transaction(static function () use ($callback, $recorder): mixed {
                if ($recorder->isOutermost()) {
                    $recorder->resetAttempt();
                }

                $result = $callback();

                if ($recorder->isOutermost()) {
                    $recorder->flush();
                }

                return $result;
            }, $attempts);
        } finally {
            $recorder->leave();
        }
    }
}
