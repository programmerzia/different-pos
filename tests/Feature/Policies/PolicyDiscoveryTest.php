<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Gate;

it('has a policy for every tenant model that gets a controller', function (): void {
    $models = [
        'Category', 'Brand', 'Unit', 'TaxRate', 'ProductFamily', 'Product', 'CustomerGroup', 'Customer', 'MembershipCard',
        'Vendor', 'PaymentMethod', 'Register', 'Purchase', 'StockAdjustment', 'Sale', 'SaleReturn', 'Payment', 'Message',
        'User', 'Branch', 'Tenant',
    ];

    foreach ($models as $model) {
        $policy = app(Gate::class)->getPolicyFor('App\\Models\\'.$model);
        expect($policy)->not->toBeNull("no policy for {$model}")
            ->and($policy::class)->toBe("App\\Policies\\{$model}Policy");
    }
});
