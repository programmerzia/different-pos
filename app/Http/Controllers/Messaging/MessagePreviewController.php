<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Message;
use App\Sms\BroadcastAudience;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /app/messages/preview?customer={uuid}: the template values for one customer, from the same
 * source the send path uses (BroadcastAudience::values), so the composer's phone preview shows the
 * customer's live due rather than a sample.
 */
final class MessagePreviewController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, BroadcastAudience $audience): JsonResponse
    {
        $this->authorize('send', Message::class);

        $uuid = (string) $request->query('customer', '');
        if ($uuid === '') {
            return response()->json(['values' => null]); // no customer picked: the composer shows samples
        }

        $customer = Customer::query()->where('uuid', $uuid)->firstOrFail();

        return response()->json(['values' => $audience->values($customer)]);
    }
}
