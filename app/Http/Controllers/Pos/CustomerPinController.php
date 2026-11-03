<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Actions\Customers\SetCustomerPin;
use App\Actions\Customers\VerifyCustomerPin;
use App\Exceptions\Domain\InvalidCustomerPin;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Support\Customers\CustomerPin;
use App\Support\Pos\MemberToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The register's member PIN flow (02_AppFlow §4.4, online part): verify → member token for this cart;
 * set → a member chooses their first PIN at the counter. Both answer PIN *state* only.
 */
final class CustomerPinController extends Controller
{
    /** POST /app/api/pos/customers/{customer}/verify-pin {pin, client_uuid} */
    public function verify(Request $request, string $customer, VerifyCustomerPin $verify, MemberToken $tokens): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $user = $this->actor($request);
        $model = $this->find($customer);
        $data = $request->validate([
            'pin' => ['required', 'string', 'max:6'],
            'client_uuid' => ['required', 'uuid'],
        ]);

        $result = $verify->handle($user, $model, (string) $data['pin']);
        $body = $result->toArray();
        if ($result->ok()) {
            $body['token'] = $tokens->issue($model, $user, (string) $data['client_uuid']);
            $body['expires_in'] = MemberToken::TTL_SECONDS;
        }

        return response()->json($body, $result->ok() ? 200 : 422);
    }

    /** POST /app/api/pos/customers/{customer}/pin {pin, pin_confirmation}: a first PIN (pos.sell); replacing one needs customers.update. */
    public function set(Request $request, string $customer, SetCustomerPin $set): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $user = $this->actor($request);
        $model = $this->find($customer);
        $data = $request->validate([
            'pin' => ['required', 'string', 'max:6'],
            'pin_confirmation' => ['required', 'string', 'max:6'],
        ]);

        try {
            $model = $set->handle($user, $model, (string) $data['pin'], (string) $data['pin_confirmation'], firstPinOnly: true);
        } catch (InvalidCustomerPin $e) {
            return response()->json(['message' => __('app.customer_pin.errors.'.$e->kind), 'code' => $e->kind], 422);
        }

        return response()->json(['pin_status' => CustomerPin::status($model)]);
    }

    private function find(string $uuid): Customer
    {
        return Customer::query()->where('uuid', $uuid)->where('is_walk_in', false)->where('is_active', true)->firstOrFail();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
