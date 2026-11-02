<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Support\Customers\CustomerSearch;
use App\Support\Customers\CustomerSearchResult;
use App\Support\Pos\ProductLookup;
use App\Support\Pos\RegisterCustomer;
use App\Support\Pos\TodaySummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Read-only JSON for the online register: catalogue, customer picker, attached customer, today's takings. */
final class LookupController extends Controller
{
    /** GET /app/api/pos/products?q=&category= */
    public function products(Request $request, ProductLookup $lookup): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        $category = $request->query('category');

        return response()->json($lookup->find(
            (string) $request->query('q', ''),
            is_numeric($category) ? (int) $category : null,
        ));
    }

    /** GET /app/api/pos/customers?q= */
    public function customers(Request $request, CustomerSearch $search): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        $q = ltrim(trim((string) $request->query('q', '')), '#');
        if (mb_strlen($q) < 2) {
            return response()->json(['customers' => []]);
        }

        $rows = $search->search(mb_substr($q, 0, 60))
            ->filter(static fn (CustomerSearchResult $c): bool => $c->isActive)
            ->map(static fn (CustomerSearchResult $c): array => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'number' => $c->customerNo,
                'name' => $c->name,
                'name_bn' => $c->nameBn,
                'shop_name' => $c->shopName,
                'phone' => $c->phone,
            ])->values()->all();

        return response()->json(['customers' => $rows]);
    }

    /** GET /app/api/pos/customers/{customer}: the card shown once attached. */
    public function customer(string $customer, RegisterCustomer $cards): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        // Bound here, not by the router, so the tenant scope decides the 404.
        $customer = Customer::query()->where('uuid', $customer)->where('is_walk_in', false)->firstOrFail();

        return response()->json(['customer' => $cards->card($customer)]);
    }

    /** GET /app/api/pos/today?register_id= */
    public function today(Request $request, TodaySummary $summary): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $register = $request->query('register_id');

        return response()->json($summary->for($user, is_numeric($register) ? (int) $register : null));
    }
}
