<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Models\ProductFamily;
use App\Models\User;
use App\Support\Http\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

final class AttachProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $family = $this->route('family');

        return $user instanceof User && $family instanceof ProductFamily && $user->can('update', $family);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', ScopedExists::in('products', softDeletes: true)],
            'option1_value_id' => ['required', 'integer', ScopedExists::in('variant_option_values')],
            'option2_value_id' => ['nullable', 'integer', ScopedExists::in('variant_option_values')],
        ];
    }
}
