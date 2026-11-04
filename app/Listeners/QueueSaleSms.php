<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Sales\ShareSaleReceipt;
use App\Events\SaleCompleted;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Sms\MessageContext;
use App\Sms\SendSms;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Throwable;

/** Queues the sale-completed SMS for a named customer with a phone. Never breaks the sale. */
final readonly class QueueSaleSms
{
    public function __construct(private TenantContext $context, private MessageContext $messages) {}

    public function handle(SaleCompleted $event): void
    {
        try {
            $settings = SmsSettings::fromTenant($this->context->tenant());
            if (! $settings->sends(Templates::SALE_COMPLETED)) {
                return;
            }

            $sale = Sale::query()->withoutBranchScope()->find($event->saleId);
            $customer = $sale instanceof Sale ? Customer::query()->find($sale->customer_id) : null;
            if (! $sale instanceof Sale || ! $customer instanceof Customer || ! $this->messages->reachable($customer)) {
                return;
            }

            $body = Templates::render($settings->body(Templates::SALE_COMPLETED), $this->messages->values($customer, [
                'invoice' => (string) $sale->number,
                'total' => $sale->total->format(symbol: false),
                'paid' => $sale->paid->format(symbol: false),
                'due' => $sale->due->format(symbol: false),
                'link' => $this->link($sale),
            ]));
            SendSms::enqueue(Templates::SALE_COMPLETED, $customer->phone, $body, $customer, $sale);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The absolute public receipt URL (/r/{token}) from APP_URL, so it also works in the queue worker and in
     * this after-commit listener where there is no request. CompleteSale always sets the token; a sale without
     * one (imported) gets it through ShareSaleReceipt when a signed-in user is around, else the link stays empty.
     */
    private function link(Sale $sale): string
    {
        $token = $sale->public_token;
        if ($token === null) {
            $actor = Auth::user();
            if (! $actor instanceof User) {
                return '';
            }
            $token = app(ShareSaleReceipt::class)->handle($actor, $sale);
        }

        return route('receipts.public', $token);
    }
}
