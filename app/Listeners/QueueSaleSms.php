<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Models\Customer;
use App\Models\Sale;
use App\Sms\MessageContext;
use App\Sms\SendSms;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Tenancy\TenantContext;
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
            ]));
            SendSms::enqueue(Templates::SALE_COMPLETED, $customer->phone, $body, $customer, $sale);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
