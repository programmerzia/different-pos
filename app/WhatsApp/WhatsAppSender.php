<?php

declare(strict_types=1);

namespace App\WhatsApp;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Delivers one WhatsApp `messages` row through the current tenant's gateway and records the outcome.
 * Needs a tenant context (a request, or the WithTenantContext job middleware).
 */
final readonly class WhatsAppSender
{
    public function __construct(private WhatsAppManager $manager, private TenantContext $context) {}

    /**
     * @param  bool  $final  false = a retryable failure leaves the row queued for the next attempt
     */
    public function deliver(Message $message, bool $final = true): WhatsAppResult
    {
        $settings = WhatsAppSettings::fromTenant($this->context->tenant());
        $result = $this->manager->make($settings)->send(new WhatsAppMessage($message->phone, $message->body, 'm'.$message->id));

        $message->driver = $settings->driver;
        if ($result->ok) {
            $message->status = $result->status;
            $message->provider_ref = $result->providerRef !== null ? mb_substr($result->providerRef, 0, 100) : null;
            $message->error = null;
            $message->sent_at = Carbon::now();
        } elseif ($result->retryable && ! $final) {
            $message->error = $result->error;
        } else {
            $message->status = MessageStatus::Failed;
            $message->error = $result->error;
        }
        $message->save();

        return $result;
    }
}
