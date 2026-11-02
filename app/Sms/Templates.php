<?php

declare(strict_types=1);

namespace App\Sms;

/** The transactional SMS templates: keys, default English bodies and the placeholders each one offers. */
final class Templates
{
    public const string SALE_COMPLETED = 'sale_completed';

    public const string PAYMENT_RECEIVED = 'payment_received';

    public const string DUE_REMINDER = 'due_reminder';

    public const array DEFAULTS = [
        self::SALE_COMPLETED => 'Thank you {customer}! Invoice {invoice} total ৳{total}, paid ৳{paid}, due ৳{due}. — {shop}',
        self::PAYMENT_RECEIVED => 'Dear {customer}, we received ৳{amount}. Remaining due ৳{due}. — {shop}',
        self::DUE_REMINDER => 'Dear {customer}, your outstanding due is ৳{due}. Please pay at your earliest convenience. — {shop}',
    ];

    /** Placeholders per template (also the legend on the settings page). */
    public const array PLACEHOLDERS = [
        self::SALE_COMPLETED => ['customer', 'shop', 'invoice', 'total', 'paid', 'due'],
        self::PAYMENT_RECEIVED => ['customer', 'shop', 'amount', 'due', 'balance'],
        self::DUE_REMINDER => ['customer', 'shop', 'due', 'balance'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /**
     * Replaces {name} placeholders; unknown ones stay as typed so a typo is visible in the test SMS.
     *
     * @param  array<string, string>  $values
     */
    public static function render(string $body, array $values): string
    {
        $pairs = [];
        foreach ($values as $name => $value) {
            $pairs['{'.$name.'}'] = $value;
        }

        return strtr($body, $pairs);
    }
}
