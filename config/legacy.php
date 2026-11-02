<?php

declare(strict_types=1);

use App\Support\Permissions\SystemRoles;

/*
| Store-specific facts for `php artisan legacy:import` (ADR 0002 M1). The importer code is
| store-generic: onboarding another legacy store is a new entry here plus a rehearsal.
*/
return [

    // legacy store_admin_group.id => new system role name; unknown groups fall back to Cashier (reported).
    'admin_groups' => [
        1 => SystemRoles::OWNER,
        2 => SystemRoles::MANAGER,
        3 => SystemRoles::CASHIER,
        4 => SystemRoles::ACCOUNTANT,
    ],

    // Target tenant slug => its name and the legacy stores that become its branches.
    // A store without an entry gets branch code "L{store}" and the legacy store name.
    'tenants' => [
        'different-fashion' => [
            'name' => 'DIFFERENT FASHION',
            'stores' => [
                7 => ['code' => 'DF1', 'name' => 'Main'],
                10 => ['code' => 'DF2', 'name' => 'Branch 2'],
            ],
        ],
    ],

    'report_dir' => storage_path('app/migration'),

    'chunk' => 500,
];
