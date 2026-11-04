<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Enums\ProductItemType;
use App\Models\Product;
use App\Models\User;
use App\Support\Http\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The GRN's inline "New item": the few fields a buyer knows at the counter (money in paisa). */
final class QuickItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('item_type')) {
            $this->merge(['item_type' => ProductItemType::RawMaterial->value]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $finished = $this->input('item_type') === ProductItemType::Finished->value;

        return [
            'name' => ['required', 'string', 'max:190'],
            'item_type' => ['required', Rule::enum(ProductItemType::class)],
            'unit_id' => ['required', 'integer', ScopedExists::in('units')],
            'cost_price' => ['nullable', 'integer', 'min:0'],
            'sale_price' => [$finished ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }
}
