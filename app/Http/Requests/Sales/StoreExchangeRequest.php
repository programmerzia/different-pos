<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Return lines plus a replacement cart (qty in milli-units, money in paisa). Needs returns.create + pos.sell. */
final class StoreExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', SaleReturn::class) && $user->can('create', Sale::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $context = app(TenantContext::class);

        return [
            ...StoreSaleReturnRequest::returnRules(),
            'reason' => ['nullable', Rule::in(StoreSaleReturnRequest::REASONS)],
            'surplus_mode' => ['required', Rule::in(['refund', 'credit'])],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $context->tenantId())->where('is_active', true)],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'tenders' => ['nullable', 'array', 'max:10'],
            'tenders.*.payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('tenant_id', $context->tenantId())->where('is_active', true)],
            'tenders.*.amount' => ['required', 'integer', 'min:1'],
            'tenders.*.reference' => ['nullable', 'string', 'max:100'],
            'expected_total' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
