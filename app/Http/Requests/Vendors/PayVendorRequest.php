<?php

declare(strict_types=1);

namespace App\Http\Requests\Vendors;

use App\Models\User;
use App\Support\Http\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

/** Pay a vendor (from the vendor profile: oldest dues first; from a purchase: that purchase first). */
final class PayVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('payments.pay');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['required', 'integer', ScopedExists::in('payment_methods')],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
