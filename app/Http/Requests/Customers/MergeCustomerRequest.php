<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Merge the route customer (the duplicate) into the survivor named by uuid. */
final class MergeCustomerRequest extends FormRequest
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
            'into' => ['required', 'uuid', Rule::exists('customers', 'uuid')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
        ];
    }
}
