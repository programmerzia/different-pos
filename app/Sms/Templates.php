<?php

declare(strict_types=1);

namespace App\Sms;

/** The transactional SMS templates: keys, default English bodies and the placeholders each one offers. */
final class Templates
{
    public const string SALE_COMPLETED = 'sale_completed';

    public const string PAYMENT_RECEIVED = 'payment_received';

    public const string DUE_REMINDER = 'due_reminder';

    public const string SALE_RETURNED = 'sale_returned';

    public const string SALE_VOIDED = 'sale_voided';

    public const array DEFAULTS = [
        self::SALE_COMPLETED => 'Thank you {customer}! Invoice {invoice} total Tk {total}, paid Tk {paid}, due Tk {due}. View: {link} - {shop}',
        self::PAYMENT_RECEIVED => 'Dear {customer}, we received Tk {amount}. Remaining due Tk {due}. - {shop}',
        self::DUE_REMINDER => 'Dear {customer}, your outstanding due is Tk {due}. Please pay at your earliest convenience. - {shop}',
        self::SALE_RETURNED => 'Dear {customer}, your return on invoice {invoice} is recorded: Tk {amount}. Remaining due Tk {due}. - {shop}',
        self::SALE_VOIDED => 'Dear {customer}, invoice {invoice} (Tk {total}) has been cancelled. - {shop}',
    ];

    /**
     * Earlier default bodies per template. A tenant whose stored body is exactly one of these never customised
     * it, so SmsSettings treats it as the current default (a customised body is left alone).
     */
    public const array SUPERSEDED_DEFAULTS = [
        self::SALE_COMPLETED => [
            'Thank you {customer}! Invoice {invoice} total ৳{total}, paid ৳{paid}, due ৳{due}. — {shop}',
            'Thank you {customer}! Invoice {invoice} total ৳{total}, paid ৳{paid}, due ৳{due}. View: {link} — {shop}',
        ],
        self::PAYMENT_RECEIVED => [
            'Dear {customer}, we received ৳{amount}. Remaining due ৳{due}. — {shop}',
        ],
        self::DUE_REMINDER => [
            'Dear {customer}, your outstanding due is ৳{due}. Please pay at your earliest convenience. — {shop}',
        ],
        self::SALE_RETURNED => [
            'Dear {customer}, your return on invoice {invoice} is recorded: ৳{amount}. Remaining due ৳{due}. — {shop}',
        ],
        self::SALE_VOIDED => [
            'Dear {customer}, invoice {invoice} (৳{total}) has been cancelled. — {shop}',
        ],
    ];

    /**
     * The WhatsApp reminder / thank-you texts offered in the WhatsApp dialog (editable per send). Bangla by
     * default, as the shop's customers read it; the same placeholders as the SMS templates plus {shop_phone}
     * and {last_invoice}.
     */
    public const array WHATSAPP_DEFAULTS = [
        self::DUE_REMINDER => 'প্রিয় {name}, {shop}-এ আপনার পূর্বের বকেয়া {due} টাকা পরিশোধের জন্য বিনীত অনুরোধ জানানো হচ্ছে। ধন্যবাদ, {shop} ({shop_phone})।',
        self::PAYMENT_RECEIVED => 'প্রিয় {name}, আপনার {amount} টাকা পরিশোধের জন্য ধন্যবাদ। বর্তমান বকেয়া {due} টাকা। — {shop} ({shop_phone})',
    ];

    /** Placeholders per template (also the legend on the settings page). */
    public const array PLACEHOLDERS = [
        self::SALE_COMPLETED => ['customer', 'shop', 'invoice', 'total', 'paid', 'due', 'link'],
        self::PAYMENT_RECEIVED => ['customer', 'shop', 'amount', 'due', 'balance'],
        self::DUE_REMINDER => ['customer', 'shop', 'due', 'balance'],
        self::SALE_RETURNED => ['customer', 'shop', 'invoice', 'amount', 'due', 'balance'],
        self::SALE_VOIDED => ['customer', 'shop', 'invoice', 'total', 'due', 'balance'],
    ];

    /** Always filled from the customer and the shop (MessageContext::values), on every message. */
    public const array ALWAYS_FILLED = ['customer', 'name', 'shop', 'shop_phone', 'due', 'balance'];

    /**
     * The {placeholders} in a body that the given values cannot fill. Such a message must not go out: the
     * customer would read the raw text.
     *
     * @param  list<string>  $fillable
     * @return list<string>
     */
    public static function unfilled(string $body, array $fillable): array
    {
        preg_match_all('/\{([A-Za-z_]+)\}/', $body, $matches);

        return array_values(array_unique(array_diff($matches[1], $fillable)));
    }

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
