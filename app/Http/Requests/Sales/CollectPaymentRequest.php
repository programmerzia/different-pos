<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\Payment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A due collection: amount in paisa, a money payment method of this tenant, and optionally a
 * manual allocation (sale id + paisa, sales of the current branch). No allocations = oldest first.
 */
final class CollectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('collect', Payment::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $context = app(TenantContext::class);

        return [
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('tenant_id', $context->tenantId())->where('is_active', true)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'allocations' => ['nullable', 'array', 'max:500'],
            'allocations.*.sale_id' => ['required', 'integer', 'distinct', Rule::exists('sales', 'id')->where('tenant_id', $context->tenantId())->where('branch_id', $context->branchId())],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
