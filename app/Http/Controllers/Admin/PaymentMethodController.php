<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\SavePaymentMethod;
use App\Enums\PaymentMethodType;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /app/settings/payment-methods (settings.manage via route middleware). */
final class PaymentMethodController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:settings.manage')];
    }

    use TranslatesDomainErrors;

    public function index(): Response
    {
        Gate::authorize('viewAny', PaymentMethod::class);

        return Inertia::render('settings/payment-methods', [
            'methods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('name')->get()->map(static fn (PaymentMethod $m): array => [
                'id' => $m->id,
                'name' => $m->name,
                'type' => $m->type->value,
                'requires_reference' => $m->requires_reference,
                'sort_order' => $m->sort_order,
                'is_active' => $m->is_active,
            ])->all(),
            'types' => array_map(static fn (PaymentMethodType $t): string => $t->value, PaymentMethodType::cases()),
        ]);
    }

    public function store(Request $request, SavePaymentMethod $save): RedirectResponse
    {
        Gate::authorize('create', PaymentMethod::class);
        $this->guarded(fn (): PaymentMethod => $save->handle($this->actor($request), $this->validated($request)));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.created')]);

        return back();
    }

    public function update(Request $request, int $method, SavePaymentMethod $save): RedirectResponse
    {
        $model = PaymentMethod::query()->findOrFail($method);
        Gate::authorize('update', $model);
        $this->guarded(fn (): PaymentMethod => $save->handle($this->actor($request), $this->validated($request), $model));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.updated')]);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['sometimes', Rule::enum(PaymentMethodType::class)],
            'requires_reference' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:-1000,1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
