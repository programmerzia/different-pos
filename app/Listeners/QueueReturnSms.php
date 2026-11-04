<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SaleReturned;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Sms\MessageContext;
use App\Sms\SendSms;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Tenancy\TenantContext;
use Throwable;

/** Queues the sale-returned SMS for a named customer with a phone. Never breaks the return. */
final readonly class QueueReturnSms
{
    public function __construct(private TenantContext $context, private MessageContext $messages) {}

    public function handle(SaleReturned $event): void
    {
        try {
            $settings = SmsSettings::fromTenant($this->context->tenant());
            if (! $settings->sends(Templates::SALE_RETURNED)) {
                return;
            }

            $return = SaleReturn::query()->withoutBranchScope()->find($event->returnId);
            $customer = Customer::query()->find($event->customerId);
            if (! $return instanceof SaleReturn || ! $customer instanceof Customer || ! $this->messages->reachable($customer)) {
                return;
            }
            $sale = Sale::query()->withoutBranchScope()->find($return->sale_id);

            // The value handed back to the customer: refunded over the counter plus credited to the account.
            $body = Templates::render($settings->body(Templates::SALE_RETURNED), $this->messages->values($customer, [
                'invoice' => (string) ($sale instanceof Sale ? $sale->number : $return->number),
                'amount' => $return->refunded->plus($return->credited)->format(symbol: false),
            ]));
            SendSms::enqueue(Templates::SALE_RETURNED, $customer->phone, $body, $customer, $return);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
