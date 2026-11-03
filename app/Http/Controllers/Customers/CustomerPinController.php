<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\ResetCustomerPin;
use App\Actions\Customers\SetCustomerPin;
use App\Actions\Customers\UnlockCustomerPin;
use App\Exceptions\Domain\InvalidCustomerPin;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Customer membership PIN from the profile (02_AppFlow rows 52a, 53, 54): set (customers.update,
 * staff-assisted: the customer types the PIN twice), reset and unlock (customers.reset_pin + reason).
 * The PIN travels only in the POST body and is never echoed back, logged or flashed.
 */
final class CustomerPinController extends Controller
{
    /** POST /app/customers/{customer}/pin */
    public function set(Request $request, string $customer, SetCustomerPin $set): RedirectResponse|JsonResponse
    {
        $model = $this->find($customer);
        Gate::authorize('update', $model);
        $data = $request->validate([
            'pin' => ['required', 'string', 'max:6'],
            'pin_confirmation' => ['required', 'string', 'max:6'],
        ]);

        try {
            $set->handle($this->actor($request), $model, (string) $data['pin'], (string) $data['pin_confirmation']);
        } catch (InvalidCustomerPin $e) {
            return $this->invalid($request, $e);
        }

        return $this->done($request, __('app.customer_pin.set', ['name' => $model->name]));
    }

    /** POST /app/customers/{customer}/pin/reset: reason required; a new PIN (typed twice) is optional. */
    public function reset(Request $request, string $customer, ResetCustomerPin $reset): RedirectResponse|JsonResponse
    {
        $model = $this->find($customer);
        Gate::authorize('resetPin', $model);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:200'],
            'pin' => ['nullable', 'string', 'max:6'],
            'pin_confirmation' => ['nullable', 'string', 'max:6'],
        ]);
        $pin = isset($data['pin']) && $data['pin'] !== '' ? (string) $data['pin'] : null;

        try {
            $reset->handle($this->actor($request), $model, (string) $data['reason'], $pin, $pin === null ? null : (string) ($data['pin_confirmation'] ?? ''));
        } catch (InvalidCustomerPin $e) {
            return $this->invalid($request, $e);
        }

        return $this->done($request, __($pin === null ? 'app.customer_pin.reset' : 'app.customer_pin.set', ['name' => $model->name]));
    }

    /** POST /app/customers/{customer}/pin/unlock */
    public function unlock(Request $request, string $customer, UnlockCustomerPin $unlock): RedirectResponse|JsonResponse
    {
        $model = $this->find($customer);
        Gate::authorize('resetPin', $model);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']]);

        try {
            $unlock->handle($this->actor($request), $model, (string) $data['reason']);
        } catch (InvalidCustomerPin $e) {
            return $this->invalid($request, $e);
        }

        return $this->done($request, __('app.customer_pin.unlocked', ['name' => $model->name]));
    }

    private function find(string $uuid): Customer
    {
        // Bound here, not by the router, so the tenant scope decides the 404.
        return Customer::query()->where('uuid', $uuid)->where('is_walk_in', false)->firstOrFail();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function done(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function invalid(Request $request, InvalidCustomerPin $e): JsonResponse
    {
        $message = __('app.customer_pin.errors.'.$e->kind);
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'code' => $e->kind], 422);
        }

        throw ValidationException::withMessages([$e->kind === 'reason' ? 'reason' : 'pin' => $message]);
    }
}
