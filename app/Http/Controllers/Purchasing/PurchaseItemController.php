<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Actions\Catalog\SaveProduct;
use App\Enums\ProductItemType;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\QuickItemRequest;
use App\Models\Product;
use App\Models\User;
use App\Support\Catalog\ProductLookup;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Http\JsonResponse;

/** POST /app/purchases/items: create a product or raw material from the GRN without leaving it. */
final class PurchaseItemController extends Controller
{
    use TranslatesDomainErrors;

    public function store(QuickItemRequest $request, SaveProduct $save, ProductLookup $lookup): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        /** @var array<string, mixed> $v */
        $v = $request->validated();
        $canCost = $user->can('products.view_cost');

        $data = [
            'name' => (string) $v['name'],
            'item_type' => ProductItemType::from((string) $v['item_type']),
            'unit_id' => (int) $v['unit_id'],
            'sale_price' => Money::ofPaisa((int) ($v['sale_price'] ?? 0)),
        ];
        if ($canCost && isset($v['cost_price'])) {
            $data['cost_price'] = Money::ofPaisa((int) $v['cost_price']);
        }

        $product = $this->guarded(fn (): Product => $save->handle($user, $data), 'name')->refresh();

        return response()->json($lookup->present($product, $canCost, Quantity::zero()), 201);
    }
}
