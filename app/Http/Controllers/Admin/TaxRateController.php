<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Catalog\SaveTaxRate;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/** /app/settings/taxes: VAT rates in basis points (settings.manage via route middleware). */
final class TaxRateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:settings.manage')];
    }

    use TranslatesDomainErrors;

    public function index(): Response
    {
        return Inertia::render('settings/taxes', [
            'taxes' => TaxRate::query()->orderBy('name')->get()->map(static fn (TaxRate $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'rate_bp' => $t->rate_bp,
                'is_default' => $t->is_default,
                'is_active' => $t->is_active,
            ])->all(),
        ]);
    }

    public function store(Request $request, SaveTaxRate $save): RedirectResponse
    {
        $this->guarded(fn (): TaxRate => $save->handle($this->actor($request), $this->validated($request)));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.created')]);

        return back();
    }

    public function update(Request $request, int $tax, SaveTaxRate $save): RedirectResponse
    {
        $model = TaxRate::query()->findOrFail($tax);
        $this->guarded(fn (): TaxRate => $save->handle($this->actor($request), $this->validated($request), $model));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.updated')]);

        return back();
    }

    /** @return array{name: string, rate_bp: int, is_default: bool, is_active: bool} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'rate_bp' => ['required', 'integer', 'between:0,10000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'name' => (string) $data['name'],
            'rate_bp' => (int) $data['rate_bp'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
