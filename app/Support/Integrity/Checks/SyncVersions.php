<?php

declare(strict_types=1);

namespace App\Support\Integrity\Checks;

use App\Models\Concerns\Syncable;
use App\Support\Integrity\Check;
use App\Support\Integrity\CheckResult;
use Illuminate\Database\Eloquent\Model;

/** No row of a Syncable model may have a null sync_version once committed. Refs read "table:id". */
final class SyncVersions extends Check
{
    public function name(): string
    {
        return 'sync_versions';
    }

    public function run(int $tenantId): CheckResult
    {
        $selects = array_map(
            static fn (string $table): string => "SELECT CONCAT('{$table}:', id) AS ref FROM `{$table}` WHERE tenant_id = {$tenantId} AND sync_version IS NULL",
            $this->tables(),
        );

        return $this->failing(implode(' UNION ALL ', $selects));
    }

    /** @return list<string> */
    private function tables(): array
    {
        $tables = [];
        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if (is_subclass_of($class, Model::class) && in_array(Syncable::class, \class_uses_recursive($class), true)) {
                $tables[] = (new $class)->getTable();
            }
        }
        sort($tables);

        return $tables;
    }
}
