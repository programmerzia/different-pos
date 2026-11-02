<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Return lines carry quantities in milli-units (1 unit = 1000). */
final class StoreSaleReturnRequest extends FormRequest
{
    public const array REASONS = ['defect', 'size', 'colour', 'changed_mind', 'wrong_item', 'exchange', 'other'];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', SaleReturn::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...self::returnRules(),
            'refund_mode' => ['required', Rule::in(['refund', 'credit'])],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function returnRules(): array
    {
        $context = app(TenantContext::class);

        return [
            'sale_id' => ['required', 'integer', Rule::exists('sales', 'id')->where('tenant_id', $context->tenantId())->where('branch_id', $context->branchId())],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.sale_item_id' => ['required', 'integer', 'distinct'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'lines.*.restock' => ['required', 'boolean'],
            'refund_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('tenant_id', $context->tenantId())],
            'reason' => ['required', Rule::in(self::REASONS)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
