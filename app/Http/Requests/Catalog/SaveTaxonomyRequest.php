<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Support\Http\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

/** Category / brand create + update (the route decides which). */
final class SaveTaxonomyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }
        $model = $this->route('category') ?? $this->route('brand');

        if ($model instanceof Category || $model instanceof Brand) {
            return $user->can('update', $model);
        }

        return $user->can('create', $this->routeIs('brands.*') ? Brand::class : Category::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', ScopedExists::in('categories', softDeletes: true)],
            'is_active' => ['boolean'],
        ];
    }
}
