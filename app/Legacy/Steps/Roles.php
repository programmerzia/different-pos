<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Actions\Tenancy\SeedTenantRoles;
use App\Legacy\ImportContext;
use App\Support\Permissions\SystemRoles;
use Spatie\Permission\Models\Role;

/** Step 2: the tenant gets the system roles; legacy admin groups map onto them via config/legacy.php. */
final readonly class Roles implements Step
{
    public function __construct(private SeedTenantRoles $seedRoles) {}

    public function name(): string
    {
        return 'Roles';
    }

    public function run(ImportContext $context): void
    {
        $before = Role::query()->where('tenant_id', $context->tenantId())->count();
        $roles = count($this->seedRoles->handle($context->tenant()));
        $created = Role::query()->where('tenant_id', $context->tenantId())->count() - $before;
        $context->created($created);
        $context->skipped($roles - $created);

        /** @var array<int, string> $map */
        $map = (array) config('legacy.admin_groups', []);
        $groups = $context->select('store_admin_group', ['id'], ['title'])->orderBy('id')->get();
        foreach ($groups as $group) {
            if (! isset($map[(int) $group->id])) {
                $title = $context->text($group->title, 'store_admin_group', (int) $group->id, 'title');
                $context->report->add('unmapped_group', 'store_admin_group', (int) $group->id, "Group [{$title}] has no role mapping; its users become ".SystemRoles::CASHIER);
            }
        }
    }
}
