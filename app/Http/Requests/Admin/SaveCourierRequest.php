<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveCourierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('settings.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $editing = (int) $this->route('courier');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('couriers', 'name')->where('tenant_id', app(TenantContext::class)->tenantId())->ignore($editing ?: null)],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
