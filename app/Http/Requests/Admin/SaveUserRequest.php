<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\Phone\BdPhone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

final class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('users.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $editing = (int) $this->route('user');
        $unique = fn (string $column): Unique => Rule::unique('users', $column)->ignore($editing ?: null);

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($editing ?: null)],
            'username' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', $unique('username')->where('tenant_id', $tenantId)],
            'phone' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail) use ($tenantId, $editing): void {
                $e164 = is_string($value) ? BdPhone::toE164($value) : null;
                if ($e164 === null) {
                    $fail(__('validation.regex', ['attribute' => $attribute]));

                    return;
                }
                if (User::query()->where('tenant_id', $tenantId)->where('phone', $e164)->whereKeyNot($editing)->exists()) {
                    $fail(__('validation.unique', ['attribute' => $attribute]));
                }
            }],
            'password' => [$editing ? 'nullable' : 'required', 'string', Password::default()],
            'is_active' => ['sometimes', 'boolean'],
            'must_change_password' => ['sometimes', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer'],
        ];
    }
}
