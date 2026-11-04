<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Database\Query\Builder;

final class SalesByCategory extends AbstractSalesBreakdown
{
    public function name(): string
    {
        return 'sales-by-category';
    }

    public function filters(): array
    {
        return ['cashier', 'brand'];
    }

    protected function noneLabel(): string
    {
        return 'Uncategorised';
    }

    protected function groupKey(): string
    {
        return 'category';
    }

    protected function groupHeader(): string
    {
        return 'Category';
    }

    /** @return array{string, string} */
    protected function joinGroup(Builder $query): array
    {
        $query->leftJoin('categories', fn ($join) => $join->on('categories.id', '=', 'products.category_id')->where('categories.tenant_id', '=', $this->tenantId()));

        return ['categories.id', 'categories.name'];
    }

    protected function narrow(Builder $query, ReportParameters $parameters): void
    {
        if (($brand = $parameters->filter('brand')) !== null && ctype_digit($brand)) {
            $query->where('products.brand_id', (int) $brand);
        }
    }
}
