<?php

declare(strict_types=1);

namespace App\Http\Requests\Cards;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Customers (by uuid) to issue a card to. */
final class IssueCardsRequest extends FormRequest
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
            'customers' => ['required', 'array', 'min:1', 'max:200'],
            'customers.*' => ['uuid', Rule::exists('customers', 'uuid')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
        ];
    }
}
