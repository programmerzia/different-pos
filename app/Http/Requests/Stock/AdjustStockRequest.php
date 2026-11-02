<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use App\Enums\AdjustmentReason;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Support\Http\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `qty` is signed milli-units: positive adds stock, negative removes it. */
final class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', StockAdjustment::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', ScopedExists::in('products', softDeletes: true)],
            'items.*.qty' => ['required', 'integer', 'not_in:0'],
            'items.*.unit_cost' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
