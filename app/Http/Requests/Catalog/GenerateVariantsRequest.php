<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Models\ProductFamily;
use App\Models\User;
use App\Support\Http\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

final class GenerateVariantsRequest extends FormRequest
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
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.option1_value_id' => ['required', 'integer', ScopedExists::in('variant_option_values')],
            'rows.*.option2_value_id' => ['nullable', 'integer', ScopedExists::in('variant_option_values')],
            'rows.*.sale_price' => ['nullable', 'integer', 'min:0'],
            'rows.*.cost_price' => ['nullable', 'integer', 'min:0'],
            'rows.*.sku' => ['nullable', 'string', 'max:64'],
            'rows.*.barcode' => ['nullable', 'string', 'max:64'],
        ];
    }
}
