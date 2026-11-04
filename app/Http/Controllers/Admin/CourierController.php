<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\DeleteCourier;
use App\Actions\Settings\SaveCourier;
use App\Actions\Settings\SetDefaultCourier;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCourierRequest;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** /app/settings/couriers (settings.manage via route middleware). */
final class CourierController extends Controller implements HasMiddleware
{
    use TranslatesDomainErrors;

    public static function middleware(): array
    {
        return [new Middleware('can:settings.manage')];
    }

    public function index(): Response
    {
        Gate::authorize('viewAny', Courier::class);

        return Inertia::render('settings/couriers', [
            'couriers' => Courier::query()->orderByDesc('is_default')->orderBy('name')->get()->map(static fn (Courier $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'is_default' => $c->is_default,
                'is_active' => $c->is_active,
            ])->all(),
        ]);
    }

    public function store(SaveCourierRequest $request, SaveCourier $save): RedirectResponse
    {
        Gate::authorize('create', Courier::class);
        $this->guarded(fn (): Courier => $save->handle($this->actor($request), $request->validated()));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.created')]);

        return back();
    }

    public function update(SaveCourierRequest $request, int $courier, SaveCourier $save): RedirectResponse
    {
        $model = Courier::query()->findOrFail($courier);
        Gate::authorize('update', $model);
        $this->guarded(fn (): Courier => $save->handle($this->actor($request), $request->validated(), $model));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.updated')]);

        return back();
    }

    public function makeDefault(Request $request, int $courier, SetDefaultCourier $setDefault): RedirectResponse
    {
        $model = Courier::query()->findOrFail($courier);
        Gate::authorize('update', $model);
        $this->guarded(fn (): Courier => $setDefault->handle($this->actor($request), $model));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.updated')]);

        return back();
    }

    public function destroy(Request $request, int $courier, DeleteCourier $delete): RedirectResponse
    {
        $model = Courier::query()->findOrFail($courier);
        Gate::authorize('delete', $model);
        $delete->handle($this->actor($request), $model);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.deleted')]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
