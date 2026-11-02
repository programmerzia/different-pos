<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CreateExchange;
use App\Actions\Sales\Data\ExchangeDraft;
use App\Actions\Sales\Data\ExchangeResult;
use App\Actions\Sales\Data\SaleDraft;
use App\Actions\Sales\Data\SaleLineDraft;
use App\Actions\Sales\Data\TenderDraft;
use App\Enums\RefundMode;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\PresentsSales;
use App\Http\Requests\Sales\StoreExchangeRequest;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** POST /app/exchanges: return lines + replacement cart in one transaction (CreateExchange). */
final class ExchangeController extends Controller
{
    use AuthorizesRequests, PresentsSales, TranslatesDomainErrors;

    public function store(StoreExchangeRequest $request, CreateExchange $exchange): RedirectResponse
    {
        $this->authorize('create', SaleReturn::class);
        $this->authorize('create', Sale::class);

        $items = [];
        foreach ((array) $request->input('items', []) as $item) {
            if (is_array($item)) {
                $items[] = new SaleLineDraft((int) $item['product_id'], Quantity::ofMilli((int) $item['qty']));
            }
        }
        $tenders = [];
        foreach ((array) $request->input('tenders', []) as $tender) {
            if (is_array($tender)) {
                $reference = isset($tender['reference']) && is_string($tender['reference']) && trim($tender['reference']) !== '' ? trim($tender['reference']) : null;
                $tenders[] = new TenderDraft((int) $tender['payment_method_id'], Money::ofPaisa((int) $tender['amount']), $reference);
            }
        }

        $result = $this->guarded(fn (): ExchangeResult => $exchange->handle($this->actor($request), new ExchangeDraft(
            saleId: $request->integer('sale_id'),
            returnLines: $this->returnLines($request),
            replacement: new SaleDraft(
                lines: $items,
                tenders: $tenders,
                expectedTotal: $request->filled('expected_total') ? Money::ofPaisa($request->integer('expected_total')) : null,
            ),
            surplusMode: RefundMode::from($request->string('surplus_mode')->value()),
            refundMethodId: $request->filled('refund_method_id') ? $request->integer('refund_method_id') : null,
            reason: $request->string('reason')->value() ?: 'exchange',
            note: $request->string('note')->trim()->value() ?: null,
        )), 'lines');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('receipts.flash.exchanged', [
            'return' => $result->return->number,
            'sale' => $result->sale->number,
        ])]);

        return to_route('returns.show', $result->return->id);
    }
}
