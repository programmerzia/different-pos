<?php

declare(strict_types=1);

namespace App\Http\Requests\Vendors;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;

final class SaveVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $vendor = $this->route('vendor');

        return $user instanceof User && ($vendor instanceof Vendor ? $user->can('update', $vendor) : $user->can('create', Vendor::class));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'company' => ['nullable', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
