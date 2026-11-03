<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Http\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

/** `qty` is milli-units (positive). */
final class CreateStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', StockTransfer::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'to_branch_id' => ['required', 'integer', ScopedExists::in('branches')],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'distinct', ScopedExists::in('products', softDeletes: true)],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
