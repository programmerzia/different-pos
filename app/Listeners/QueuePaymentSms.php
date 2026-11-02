<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CustomerPaymentCollected;
use App\Models\Customer;
use App\Models\Payment;
use App\Sms\MessageContext;
use App\Sms\SendSms;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Tenancy\TenantContext;
use Throwable;

/** Queues the payment-received SMS for a named customer with a phone. Never breaks the collection. */
final readonly class QueuePaymentSms
{
    public function __construct(private TenantContext $context, private MessageContext $messages) {}

    public function handle(CustomerPaymentCollected $event): void
    {
        try {
            $settings = SmsSettings::fromTenant($this->context->tenant());
            if (! $settings->sends(Templates::PAYMENT_RECEIVED)) {
                return;
            }

            $payment = Payment::query()->withoutBranchScope()->find($event->paymentId);
            $customer = Customer::query()->find($event->customerId);
            if (! $payment instanceof Payment || ! $customer instanceof Customer || ! $this->messages->reachable($customer)) {
                return;
            }

            $body = Templates::render($settings->body(Templates::PAYMENT_RECEIVED), $this->messages->values($customer, [
                'amount' => $payment->amount->format(symbol: false),
            ]));
            SendSms::enqueue(Templates::PAYMENT_RECEIVED, $customer->phone, $body, $customer, $payment);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
