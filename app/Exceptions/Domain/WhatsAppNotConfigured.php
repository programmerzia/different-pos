<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A WhatsApp message was requested while the tenant has no WhatsApp provider, or one that cannot do the job (bulk via click-to-chat). */
final class WhatsAppNotConfigured extends DomainException
{
    public static function make(): self
    {
        return new self((string) __('sms.whatsapp.not_configured'));
    }

    public static function bulk(): self
    {
        return new self((string) __('sms.whatsapp.bulk_needs_api'));
    }
}
