<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Actions\Sales\CompleteSale;
use App\Enums\SaleOrigin;
use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StoreSaleRequest;
use App\Models\Sale;
use App\Models\User;
use App\Support\Pos\ManagerApproval;
use App\Support\Pos\MemberToken;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;

/**
 * POST /app/pos/sales: completes the register's cart through CompleteSale. A resubmit with the same
 * client_uuid returns the sale already made (200 instead of 201). Business-rule failures come back
 * as 422 with a translated headline, the server's detail and a stable code for the UI.
 */
final class SaleController extends Controller
{
    public function store(StoreSaleRequest $request, CompleteSale $complete, ManagerApproval $approval, MemberToken $members, TenantContext $context): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $approver = null;
        if ($request->filled('approval_token')) {
            $approver = $approval->resolve((string) $request->validated('approval_token'), $user);
            if (! $approver instanceof User) {
                return response()->json(['message' => __('pos.approval.expired'), 'code' => 'approval_expired'], 422);
            }
        }

        // The member token (online PIN check) must name this customer and this cart; anything else counts as no PIN.
        $memberVerified = $request->filled('customer_id') && $members->verifies(
            $request->filled('member_token') ? (string) $request->validated('member_token') : null,
            $user,
            $request->integer('customer_id'),
            (string) $request->validated('client_uuid'),
        );

        try {
            $sale = $complete->handle($user, $request->toDraft($approver, $memberVerified), SaleOrigin::Web);
        } catch (DomainException $e) {
            $code = self::code($e);

            return response()->json([
                'message' => Lang::has('pos.errors.'.$code) ? __('pos.errors.'.$code) : __('pos.errors.generic'),
                'detail' => $e->getMessage(),
                'code' => $code,
            ], 422);
        }

        $paper = (string) $context->branch()->setting('paper');

        return response()->json(['sale' => [
            'id' => $sale->id,
            'uuid' => $sale->uuid,
            'number' => $sale->number,
            'total' => $sale->total->paisa,
            'paid' => $sale->paid->paisa,
            'change' => $sale->change_given->paisa,
            'due' => $sale->due->paisa,
            'flags' => $sale->sync_flags ?? [],
            'member_auth' => $sale->member_auth->value,
            'receipt_url' => Route::has('sales.receipt') ? route('sales.receipt', ['sale' => $sale, 'paper' => $paper]) : null,
        ]], $sale->wasRecentlyCreated ? 201 : 200);
    }

    /** DiscountCapExceeded → discount_cap_exceeded. */
    private static function code(DomainException $e): string
    {
        $name = class_basename($e);

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
