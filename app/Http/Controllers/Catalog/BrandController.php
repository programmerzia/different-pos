<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\DeleteBrand;
use App\Actions\Catalog\SaveBrand;
use App\Enums\DeleteOutcome;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveTaxonomyRequest;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BrandController extends Controller
{
    use AuthorizesRequests;
    use TranslatesDomainErrors;

    public function index(): Response
    {
        $this->authorize('viewAny', Brand::class);

        $counts = Product::query()->whereNotNull('brand_id')->selectRaw('brand_id, COUNT(*) AS n')->groupBy('brand_id')->pluck('n', 'brand_id');

        return Inertia::render('catalog/taxonomy', [
            'kind' => 'brand',
            'rows' => Brand::query()->orderBy('name')->get()->map(static fn (Brand $b): array => [
                'id' => $b->id,
                'name' => $b->name,
                'name_bn' => null,
                'parent_id' => null,
                'is_active' => $b->is_active,
                'products' => (int) ($counts[$b->id] ?? 0),
            ])->all(),
        ]);
    }

    public function store(SaveTaxonomyRequest $request, SaveBrand $save): RedirectResponse
    {
        $this->guarded(fn (): Brand => $save->handle($this->actor($request), $this->data($request)));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand saved.')]);

        return back();
    }

    public function update(SaveTaxonomyRequest $request, Brand $brand, SaveBrand $save): RedirectResponse
    {
        $this->guarded(fn (): Brand => $save->handle($this->actor($request), $this->data($request), $brand));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand saved.')]);

        return back();
    }

    public function destroy(Request $request, Brand $brand, DeleteBrand $delete): RedirectResponse
    {
        $this->authorize('delete', $brand);
        $outcome = $this->guarded(fn (): DeleteOutcome => $delete->handle($this->actor($request), $brand));
        Inertia::flash('toast', ['type' => 'success', 'message' => $outcome === DeleteOutcome::Deleted ? __('Brand deleted.') : __('Brand is in use, so it was deactivated.')]);

        return back();
    }

    /** @return array{name: string, is_active: bool} */
    private function data(SaveTaxonomyRequest $request): array
    {
        return ['name' => (string) $request->validated('name'), 'is_active' => (bool) $request->validated('is_active')];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
