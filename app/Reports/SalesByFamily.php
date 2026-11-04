<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Database\Query\Builder;

/** Sales per product family (style); products without a family are one "No family" row. */
final class SalesByFamily extends AbstractSalesBreakdown
{
    public function name(): string
    {
        return 'sales-by-family';
    }

    public function filters(): array
    {
        return ['cashier', 'category', 'brand'];
    }

    protected function noneLabel(): string
    {
        return 'No family';
    }

    protected function groupKey(): string
    {
        return 'family';
    }

    protected function groupHeader(): string
    {
        return 'Family';
    }

    /** @return array{string, string} */
    protected function joinGroup(Builder $query): array
    {
        $query->leftJoin('product_families', fn ($join) => $join->on('product_families.id', '=', 'products.family_id')->where('product_families.tenant_id', '=', $this->tenantId()));

        return ['product_families.id', 'product_families.name'];
    }

    protected function narrow(Builder $query, ReportParameters $parameters): void
    {
        if (($category = $parameters->filter('category')) !== null && ctype_digit($category)) {
            $query->where('products.category_id', (int) $category);
        }
        if (($brand = $parameters->filter('brand')) !== null && ctype_digit($brand)) {
            $query->where('products.brand_id', (int) $brand);
        }
    }
}
