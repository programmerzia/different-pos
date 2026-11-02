<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Catalog\SaveUnit;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/** /app/settings/units: units of measure (settings.manage via route middleware). */
final class UnitController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:settings.manage')];
    }

    use TranslatesDomainErrors;

    public function index(): Response
    {
        return Inertia::render('settings/units', [
            'units' => Unit::query()->orderBy('name')->get()->map(static fn (Unit $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'short_name' => $u->short_name,
                'allows_decimal' => $u->allows_decimal,
                'is_active' => $u->is_active,
            ])->all(),
        ]);
    }

    public function store(Request $request, SaveUnit $save): RedirectResponse
    {
        $this->guarded(fn (): Unit => $save->handle($this->actor($request), $this->validated($request)));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.created')]);

        return back();
    }

    public function update(Request $request, int $unit, SaveUnit $save): RedirectResponse
    {
        $model = Unit::query()->findOrFail($unit);
        $this->guarded(fn (): Unit => $save->handle($this->actor($request), $this->validated($request), $model));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.updated')]);

        return back();
    }

    /** @return array{name: string, short_name: string, allows_decimal: bool, is_active: bool} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'short_name' => ['required', 'string', 'max:10'],
            'allows_decimal' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'name' => (string) $data['name'],
            'short_name' => (string) $data['short_name'],
            'allows_decimal' => (bool) ($data['allows_decimal'] ?? false),
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
