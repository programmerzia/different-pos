<?php

declare(strict_types=1);

namespace App\Sms;

use App\Models\Customer;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Throwable;

/**
 * The non-blocking "SMS not sent" info message shown after a sale, payment, return or void whose automatic
 * SMS would have gone out but the gateway is not set up. Null in every other case. Writes no `messages`
 * row and never throws: a notice must not break the business action it follows.
 */
final readonly class SmsNotice
{
    public static function missing(string $template, ?Customer $customer): ?string
    {
        try {
            if (! $customer instanceof Customer || ! app(MessageContext::class)->reachable($customer)) {
                return null;
            }

            $settings = SmsSettings::fromTenant(app(TenantContext::class)->tenant());
            if (! $settings->enabled($template) || $settings->isConfigured()) {
                return null;
            }

            return (string) __('sms.notice.not_configured');
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Flashes the notice (when there is one) as an info toast, next to the success toast. */
    public static function flash(string $template, ?Customer $customer): void
    {
        $notice = self::missing($template, $customer);
        if ($notice !== null) {
            Inertia::flash('toast_info', $notice);
        }
    }
}
