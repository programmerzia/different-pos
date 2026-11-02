<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\DeleteCategory;
use App\Actions\Catalog\SaveCategory;
use App\Enums\DeleteOutcome;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveTaxonomyRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CategoryController extends Controller
{
    use AuthorizesRequests;
    use TranslatesDomainErrors;

    public function index(): Response
    {
        $this->authorize('viewAny', Category::class);

        $counts = Product::query()->whereNotNull('category_id')->selectRaw('category_id, COUNT(*) AS n')->groupBy('category_id')->pluck('n', 'category_id');

        return Inertia::render('catalog/taxonomy', [
            'kind' => 'category',
            'rows' => Category::query()->orderBy('name')->get()->map(static fn (Category $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'name_bn' => $c->name_bn,
                'parent_id' => $c->parent_id,
                'is_active' => $c->is_active,
                'products' => (int) ($counts[$c->id] ?? 0),
            ])->all(),
        ]);
    }

    public function store(SaveTaxonomyRequest $request, SaveCategory $save): RedirectResponse
    {
        $this->guarded(fn (): Category => $save->handle($this->actor($request), $this->data($request)));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category saved.')]);

        return back();
    }

    public function update(SaveTaxonomyRequest $request, Category $category, SaveCategory $save): RedirectResponse
    {
        $this->guarded(fn (): Category => $save->handle($this->actor($request), $this->data($request), $category));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category saved.')]);

        return back();
    }

    public function destroy(Request $request, Category $category, DeleteCategory $delete): RedirectResponse
    {
        $this->authorize('delete', $category);
        $outcome = $this->guarded(fn (): DeleteOutcome => $delete->handle($this->actor($request), $category));
        Inertia::flash('toast', ['type' => 'success', 'message' => $outcome === DeleteOutcome::Deleted ? __('Category deleted.') : __('Category is in use, so it was deactivated.')]);

        return back();
    }

    /** @return array{name: string, name_bn: string|null, parent_id: int|null, is_active: bool} */
    private function data(SaveTaxonomyRequest $request): array
    {
        $parent = $request->validated('parent_id');

        return [
            'name' => (string) $request->validated('name'),
            'name_bn' => $request->validated('name_bn'),
            'parent_id' => $parent === null ? null : (int) $parent,
            'is_active' => (bool) $request->validated('is_active'),
        ];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
