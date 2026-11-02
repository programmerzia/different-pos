<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveVariantOption;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\VariantOption;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The product form's size/colour chips: add a value to an option (or the option itself) without leaving the form. */
final class VariantOptionController extends Controller
{
    use AuthorizesRequests;
    use TranslatesDomainErrors;

    public function store(Request $request, SaveVariantOption $save): JsonResponse
    {
        $this->authorize('create', Product::class);
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);

        $option = $this->guarded(fn (): VariantOption => $save->handle($this->actor($request), ['name' => $data['name']]));

        return response()->json($this->payload($option), 201);
    }

    public function addValue(Request $request, VariantOption $option, SaveVariantOption $save): JsonResponse
    {
        $this->authorize('create', Product::class);
        $data = $request->validate(['value' => ['required', 'string', 'max:60']]);

        $option = $this->guarded(fn (): VariantOption => $save->handle($this->actor($request), ['values' => [['value' => $data['value']]]], $option));

        return response()->json($this->payload($option));
    }

    /** @return array{id: int, name: string, values: list<array{id: int, value: string, code: string}>} */
    private function payload(VariantOption $option): array
    {
        return [
            'id' => $option->id,
            'name' => $option->name,
            'values' => array_values($option->values()->orderBy('sort_order')->orderBy('id')->get()
                ->map(static fn ($v): array => ['id' => $v->id, 'value' => $v->value, 'code' => $v->code])->all()),
        ];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
