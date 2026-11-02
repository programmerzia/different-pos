<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\User;
use App\Support\Pos\ManagerApproval;
use App\Support\Sales\SaleGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** POST /app/api/pos/authorize: a manager's login + password (or staff PIN) → a short-lived approval token. */
final class ManagerApprovalController extends Controller
{
    public const array PERMISSIONS = [
        'pos.discount_override', 'pos.price_override', 'pos.sell_below_cost', 'pos.customer_pin_override', 'pos.backdate',
    ];

    public function __invoke(Request $request, ManagerApproval $approval, SaleGate $gate): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $cashier = $request->user();
        abort_unless($cashier instanceof User, 401);

        $data = $request->validate([
            'login' => ['required', 'string', 'max:190'],
            'secret' => ['required', 'string', 'max:190'],
            'permission' => ['required', 'string', Rule::in(self::PERMISSIONS)],
        ]);

        $manager = $approval->verify((string) $data['login'], (string) $data['secret']);
        if (! $manager instanceof User) {
            return response()->json(['message' => __('pos.approval.invalid'), 'code' => 'invalid_credentials'], 422);
        }
        if (! $gate->allows($manager, null, (string) $data['permission'])) {
            return response()->json(['message' => __('pos.approval.not_allowed', ['name' => $manager->name]), 'code' => 'not_allowed'], 422);
        }

        return response()->json([
            'token' => $approval->issue($manager, $cashier),
            'manager' => ['id' => $manager->id, 'name' => $manager->name],
            'discount_cap_bp' => $gate->discountCapBp($cashier, $manager),
            'expires_in' => ManagerApproval::TTL_SECONDS,
        ]);
    }
}
