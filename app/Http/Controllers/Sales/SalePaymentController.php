<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CollectCustomerPayment;
use App\Actions\Sales\Data\CustomerPaymentDraft;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\PresentsSales;
use App\Http\Requests\Sales\CollectPaymentRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Sms\SmsNotice;
use App\Sms\Templates;
use App\Support\Money\Money;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** POST /app/sales/{sale}/payments: collect (part of) one invoice's due; any excess stays as an advance. */
final class SalePaymentController extends Controller
{
    use AuthorizesRequests, PresentsSales, TranslatesDomainErrors;

    public function store(CollectPaymentRequest $request, int $sale, CollectCustomerPayment $collect): RedirectResponse
    {
        $model = Sale::query()->findOrFail($sale);
        $this->authorize('view', $model);
        $this->authorize('collect', Payment::class);

        $amount = Money::ofPaisa($request->integer('amount'));
        $share = $model->due->compareTo($amount) < 0 ? $model->due : $amount;
        $payment = $this->guarded(fn (): Payment => $collect->handle($this->actor($request), new CustomerPaymentDraft(
            customerId: $model->customer_id,
            amount: $amount,
            paymentMethodId: $request->integer('payment_method_id'),
            allocations: $share->paisa > 0 ? [$model->id => $share] : [],
            reference: $request->string('reference')->trim()->value() ?: null,
            note: $request->string('note')->trim()->value() ?: null,
        )), 'amount');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('receipts.flash.collected', ['number' => $payment->number, 'amount' => $amount->format(app()->getLocale())])]);
        SmsNotice::flash(Templates::PAYMENT_RECEIVED, Customer::query()->find($model->customer_id));

        return to_route('sales.show', ['sale' => $model->id, 'receipt' => $payment->id]);
    }
}
