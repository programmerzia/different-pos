<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Database\Query\Builder;

final class SalesByBrand extends AbstractSalesBreakdown
{
    public function name(): string
    {
        return 'sales-by-brand';
    }

    public function filters(): array
    {
        return ['cashier', 'category'];
    }

    protected function noneLabel(): string
    {
        return 'No brand';
    }

    protected function groupKey(): string
    {
        return 'brand';
    }

    protected function groupHeader(): string
    {
        return 'Brand';
    }

    /** @return array{string, string} */
    protected function joinGroup(Builder $query): array
    {
        $query->leftJoin('brands', fn ($join) => $join->on('brands.id', '=', 'products.brand_id')->where('brands.tenant_id', '=', $this->tenantId()));

        return ['brands.id', 'brands.name'];
    }

    protected function narrow(Builder $query, ReportParameters $parameters): void
    {
        if (($category = $parameters->filter('category')) !== null && ctype_digit($category)) {
            $query->where('products.category_id', (int) $category);
        }
    }
}
