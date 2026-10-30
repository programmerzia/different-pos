<?php

declare(strict_types=1);

namespace App\Support\Permissions;

/** The five system roles every tenant gets (04_TRD §3.8). */
final class SystemRoles
{
    public const string OWNER = 'Owner';

    public const string MANAGER = 'Manager';

    public const string CASHIER = 'Cashier';

    public const string ACCOUNTANT = 'Accountant';

    public const string STOCK_KEEPER = 'Stock Keeper';

    /**
     * Role => [permission patterns, default max_discount_pct_bp].
     *
     * @return array<string, array{permissions: list<string>, max_discount_pct_bp: int}>
     */
    public static function definitions(): array
    {
        return [
            self::OWNER => [
                'permissions' => Permission::expand(['*']),
                'max_discount_pct_bp' => 10000,
            ],
            self::MANAGER => [
                'permissions' => array_values(array_diff(Permission::ALL, ['users.manage', 'settings.manage', 'audit.view'])),
                'max_discount_pct_bp' => 2000,
            ],
            self::CASHIER => [
                'permissions' => Permission::expand([
                    'dashboard.view', 'pos.sell', 'sales.view', 'returns.view', 'customers.view', 'customers.create',
                    'cards.view', 'products.view', 'stock.view', 'payments.collect',
                ]),
                'max_discount_pct_bp' => 0,
            ],
            self::ACCOUNTANT => [
                'permissions' => Permission::expand([
                    'dashboard.view', 'sales.view', 'returns.view', 'customers.view', 'vendors.view',
                    'money.*', 'reports.*', 'payments.*',
                ]),
                'max_discount_pct_bp' => 0,
            ],
            self::STOCK_KEEPER => [
                'permissions' => Permission::expand(['products.*', 'stock.*', 'purchases.*', 'vendors.*', 'reports.view']),
                'max_discount_pct_bp' => 0,
            ],
        ];
    }
}
