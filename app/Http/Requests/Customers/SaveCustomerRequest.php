<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create / edit customer. The controller authorizes; the group must belong to the caller's tenant. */
final class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $user = $this->user();
        $tenantId = $user instanceof User ? $user->tenant_id : null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'shop_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'credit_limit' => ['nullable', 'integer', 'min:0', 'max:9000000000000'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:100'],
            'post_code' => ['nullable', 'string', 'max:10'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'anniversary_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The validated fields in the shape SaveCustomer expects (credit limit: paisa to Money).
     *
     * @return array<string, mixed>
     */
    public function customerData(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        if (array_key_exists('credit_limit', $data)) {
            $data['credit_limit'] = $data['credit_limit'] === null ? null : Money::ofPaisa((int) $data['credit_limit']);
        }

        return $data;
    }
}
