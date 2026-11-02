<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\DeleteProduct;
use App\Actions\Catalog\GenerateFamilyVariants;
use App\Actions\Catalog\SaveProduct;
use App\Actions\Catalog\SaveProductFamily;
use App\Enums\DeleteOutcome;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantOption;
use App\Models\Vendor;
use App\Support\Catalog\ProductPresenter;
use App\Support\Database\Transaction;
use App\Support\Http\TableQuery;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class ProductController extends Controller
{
    use AuthorizesRequests;
    use TranslatesDomainErrors;

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);
        $user = $this->actor($request);
        $canCost = $user->can('products.view_cost');

        $table = TableQuery::fromRequest(
            $request,
            sortable: ['name', 'sku', 'sale_price', 'on_hand', ...($canCost ? ['cost_price'] : [])],
            filterable: ['category', 'brand', 'status'],
            defaultSort: 'name',
        );

        $branchId = $this->context->branchId();
        $query = Product::query()
            ->leftJoin('branch_stocks', static function ($join) use ($branchId): void {
                $join->on('branch_stocks.product_id', '=', 'products.id')->where('branch_stocks.branch_id', '=', $branchId);
            })
            ->selectRaw('products.*, COALESCE(branch_stocks.qty_on_hand, 0) AS on_hand')
            ->with(['category:id,name', 'brand:id,name', 'family:id,uuid']);

        if ($table->search !== '') {
            $like = '%'.addcslashes($table->search, '\\%_').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('products.name', 'like', $like)
                    ->orWhere('products.name_bn', 'like', $like)
                    ->orWhere('products.sku', 'like', $like)
                    ->orWhere('products.barcode', 'like', $like);
            });
        }
        if (isset($table->filters['category'])) {
            $query->where('products.category_id', (int) $table->filters['category']);
        }
        if (isset($table->filters['brand'])) {
            $query->where('products.brand_id', (int) $table->filters['brand']);
        }
        match ($table->filters['status'] ?? null) {
            'active' => $query->where('products.is_active', true),
            'inactive' => $query->where('products.is_active', false),
            'low' => $query->where('products.track_stock', true)
                ->where('products.reorder_level', '>', 0)
                ->whereRaw('COALESCE(branch_stocks.qty_on_hand, 0) <= products.reorder_level'),
            default => null,
        };

        $page = $table->paginate($query);

        return Inertia::render('products/index', [
            'rows' => collect($page->items())->map(static fn ($p): array => ProductPresenter::row($p, $canCost))->all(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'canViewCost' => $canCost,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('products/form', ['product' => null, ...$this->formOptions()]);
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);
        $canCost = $this->actor($request)->can('products.view_cost');
        $product->load('family:id,uuid,code,name');

        return Inertia::render('products/form', [
            'product' => ProductPresenter::detail($product, $canCost),
            ...$this->formOptions(),
            'families' => $product->family_id === null ? $this->families() : [],
            'canViewCost' => $canCost,
        ]);
    }

    public function store(SaveProductRequest $request, SaveProduct $save, SaveProductFamily $saveFamily, GenerateFamilyVariants $generate): RedirectResponse
    {
        $user = $this->actor($request);
        $data = $this->productData($request);
        $variants = $request->input('variants');

        if (is_array($variants) && $variants !== []) {
            $family = $this->guarded(fn (): mixed => Transaction::run(function () use ($user, $data, $variants, $saveFamily, $generate): ProductFamily {
                $family = $saveFamily->handle($user, [
                    'code' => $data['sku'] ?? '',
                    'name' => $data['name'] ?? '',
                    'name_bn' => $data['name_bn'] ?? null,
                    'category_id' => $data['category_id'] ?? null,
                    'brand_id' => $data['brand_id'] ?? null,
                    'unit_id' => $data['unit_id'] ?? 0,
                    'tax_rate_id' => $data['tax_rate_id'] ?? null,
                    'option1_id' => (int) $variants['option1_id'],
                    'option2_id' => isset($variants['option2_id']) ? (int) $variants['option2_id'] : null,
                    'description' => $data['description'] ?? null,
                    'image_path' => $data['image_path'] ?? null,
                ]);
                $generate->handle($user, $family, $this->variantRows($variants), [
                    'sale_price' => $data['sale_price'] ?? Money::zero(),
                    'cost_price' => $data['cost_price'] ?? Money::zero(),
                ]);

                return $family;
            }));

            return to_route('families.show', $family)->with('toast', ['type' => 'success', 'message' => __('Variants generated.')]);
        }

        $this->guarded(fn (): Product => $save->handle($user, $data));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product saved.')]);

        return to_route('products.index');
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $save): RedirectResponse
    {
        $user = $this->actor($request);
        $data = $this->productData($request, $product);
        $old = $product->image_path;

        $this->guarded(fn (): Product => $save->handle($user, $data, $product));
        if ($old !== null && $old !== ($data['image_path'] ?? $old)) {
            Storage::disk('public')->delete($old);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product saved.')]);

        return to_route('products.index');
    }

    public function active(Request $request, Product $product, SaveProduct $save): RedirectResponse
    {
        $this->authorize('update', $product);
        $save->handle($this->actor($request), ['is_active' => $request->boolean('is_active')], $product);

        return back();
    }

    public function destroy(Request $request, Product $product, DeleteProduct $delete): RedirectResponse
    {
        $this->authorize('delete', $product);
        $outcome = $this->guarded(fn (): DeleteOutcome => $delete->handle($this->actor($request), $product));
        Inertia::flash('toast', ['type' => 'success', 'message' => $outcome === DeleteOutcome::Deleted ? __('Product deleted.') : __('Product is in use, so it was deactivated.')]);

        return to_route('products.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function productData(SaveProductRequest $request, ?Product $product = null): array
    {
        /** @var array<string, mixed> $v */
        $v = $request->validated();

        $data = [
            'name' => $v['name'],
            'name_bn' => $v['name_bn'] ?? null,
            'sku' => $v['sku'],
            'barcode' => $v['barcode'] ?? null,
            'hs_code' => $v['hs_code'] ?? null,
            'type' => $v['type'],
            'category_id' => $v['category_id'] ?? null,
            'brand_id' => $v['brand_id'] ?? null,
            'unit_id' => (int) $v['unit_id'],
            'tax_rate_id' => $v['tax_rate_id'] ?? null,
            'default_vendor_id' => $v['default_vendor_id'] ?? null,
            'sale_price' => Money::ofPaisa((int) $v['sale_price']),
            'min_sale_price' => isset($v['min_sale_price']) ? Money::ofPaisa((int) $v['min_sale_price']) : null,
            'min_sale_qty' => Quantity::ofMilli((int) ($v['min_sale_qty'] ?? 0)),
            'reorder_level' => Quantity::ofMilli((int) ($v['reorder_level'] ?? 0)),
            'track_stock' => (bool) $v['track_stock'],
            'is_active' => (bool) $v['is_active'],
            'description' => $v['description'] ?? null,
        ];
        // Cost is write-protected for users without products.view_cost: the field is simply ignored.
        if ($this->actor($request)->can('products.view_cost') && isset($v['cost_price'])) {
            $data['cost_price'] = Money::ofPaisa((int) $v['cost_price']);
        }

        $image = $request->file('image');
        if ($image instanceof UploadedFile) {
            $data['image_path'] = $image->store('products', 'public');
        } elseif ($product instanceof Product && (bool) ($v['remove_image'] ?? false)) {
            $data['image_path'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $variants
     * @return list<array{option1_value_id: int, option2_value_id?: int|null, sale_price?: Money, cost_price?: Money, sku?: string, barcode?: string}>
     */
    private function variantRows(array $variants): array
    {
        $rows = [];
        /** @var list<array<string, mixed>> $input */
        $input = $variants['rows'];
        foreach ($input as $row) {
            $out = ['option1_value_id' => (int) $row['option1_value_id']];
            if (isset($row['option2_value_id'])) {
                $out['option2_value_id'] = (int) $row['option2_value_id'];
            }
            if (isset($row['sale_price'])) {
                $out['sale_price'] = Money::ofPaisa((int) $row['sale_price']);
            }
            if (isset($row['cost_price'])) {
                $out['cost_price'] = Money::ofPaisa((int) $row['cost_price']);
            }
            foreach (['sku', 'barcode'] as $key) {
                if (isset($row[$key]) && is_string($row[$key])) {
                    $out[$key] = $row[$key];
                }
            }
            $rows[] = $out;
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function families(): array
    {
        $option = static fn (?VariantOption $o): ?array => $o instanceof VariantOption ? [
            'id' => $o->id,
            'name' => $o->name,
            'values' => $o->values->map(static fn ($v): array => ['id' => $v->id, 'value' => $v->value, 'code' => $v->code])->all(),
        ] : null;

        return array_values(ProductFamily::query()->where('is_active', true)->with(['option1.values', 'option2.values'])->orderBy('name')->limit(300)->get()
            ->map(static fn (ProductFamily $f): array => ['id' => $f->id, 'uuid' => $f->uuid, 'code' => $f->code, 'name' => $f->name, 'option1' => $option($f->option1), 'option2' => $option($f->option2)])
            ->all());
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->orderBy('name')->get(['id', 'name', 'short_name', 'allows_decimal']),
            'taxRates' => TaxRate::query()->orderBy('name')->get(['id', 'name', 'rate_bp']),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'variantOptions' => VariantOption::query()->with('values:id,variant_option_id,value,code')->orderBy('sort_order')->get(['id', 'name']),
        ];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
