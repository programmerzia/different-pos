<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/** Authorization happens in the controller (`receive` on the bound transfer). `qty_received` is milli-units. */
final class ReceiveStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'items' => ['present', 'array', 'max:200'],
            'items.*.item_id' => ['required', 'integer', 'distinct'],
            'items.*.qty_received' => ['required', 'integer', 'min:0'],
            'items.*.short_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
