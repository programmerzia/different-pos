<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\AttachProductToFamily;
use App\Actions\Catalog\DetachProductFromFamily;
use App\Actions\Catalog\GenerateFamilyVariants;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\AttachProductRequest;
use App\Http\Requests\Catalog\GenerateVariantsRequest;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\User;
use App\Support\Catalog\ProductPresenter;
use App\Support\Money\Money;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class ProductFamilyController extends Controller
{
    use AuthorizesRequests;
    use TranslatesDomainErrors;

    public function show(Request $request, ProductFamily $family): Response
    {
        $this->authorize('view', $family);
        $canCost = $this->actor($request)->can('products.view_cost');
        $family->load(['option1.values', 'option2.values']);

        $variants = Product::query()->where('family_id', $family->id)->orderBy('id')->get();

        return Inertia::render('products/family', [
            'family' => [
                'id' => $family->id,
                'uuid' => $family->uuid,
                'code' => $family->code,
                'name' => $family->name,
                'image_url' => ProductPresenter::imageUrl($family->image_path),
                'option1' => $family->option1 === null ? null : [
                    'id' => $family->option1->id,
                    'name' => $family->option1->name,
                    'values' => $family->option1->values->map(static fn ($v): array => ['id' => $v->id, 'value' => $v->value, 'code' => $v->code])->all(),
                ],
                'option2' => $family->option2 === null ? null : [
                    'id' => $family->option2->id,
                    'name' => $family->option2->name,
                    'values' => $family->option2->values->map(static fn ($v): array => ['id' => $v->id, 'value' => $v->value, 'code' => $v->code])->all(),
                ],
            ],
            'variants' => $variants->map(static fn (Product $p): array => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'label' => $p->variant_label,
                'option1_value_id' => $p->option1_value_id,
                'option2_value_id' => $p->option2_value_id,
                'sale_price' => $p->sale_price->paisa,
                'cost_price' => $canCost ? $p->cost_price->paisa : null,
                'is_active' => $p->is_active,
            ])->all(),
            'candidates' => Product::query()->sellable()->whereNull('family_id')->where('is_active', true)->orderBy('name')->limit(200)->get(['id', 'name', 'sku'])->all(),
            'canViewCost' => $canCost,
        ]);
    }

    public function variants(GenerateVariantsRequest $request, ProductFamily $family, GenerateFamilyVariants $generate): RedirectResponse
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $request->validated('rows');
        $canCost = $this->actor($request)->can('products.view_cost');

        $input = [];
        foreach ($rows as $row) {
            $out = ['option1_value_id' => (int) $row['option1_value_id']];
            if (isset($row['option2_value_id'])) {
                $out['option2_value_id'] = (int) $row['option2_value_id'];
            }
            if (isset($row['sale_price'])) {
                $out['sale_price'] = Money::ofPaisa((int) $row['sale_price']);
            }
            if ($canCost && isset($row['cost_price'])) {
                $out['cost_price'] = Money::ofPaisa((int) $row['cost_price']);
            }
            foreach (['sku', 'barcode'] as $key) {
                if (isset($row[$key]) && is_string($row[$key]) && $row[$key] !== '') {
                    $out[$key] = $row[$key];
                }
            }
            $input[] = $out;
        }

        $this->guarded(fn (): Collection => $generate->handle($this->actor($request), $family, $input));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variants saved.')]);

        return to_route('families.show', $family);
    }

    public function attach(AttachProductRequest $request, ProductFamily $family, AttachProductToFamily $attach): RedirectResponse
    {
        $product = Product::query()->findOrFail((int) $request->validated('product_id'));
        $this->authorize('update', $product);
        $o2 = $request->validated('option2_value_id');

        $this->guarded(fn (): Product => $attach->handle($this->actor($request), $family, $product, (int) $request->validated('option1_value_id'), $o2 === null ? null : (int) $o2));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product added to the family.')]);

        return to_route('families.show', $family);
    }

    public function detach(Request $request, Product $product, DetachProductFromFamily $detach): RedirectResponse
    {
        $this->authorize('update', $product);
        $this->guarded(fn (): Product => $detach->handle($this->actor($request), $product));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product removed from the family.')]);

        return to_route('products.index');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
