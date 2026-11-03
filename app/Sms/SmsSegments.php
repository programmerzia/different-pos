<?php

declare(strict_types=1);

namespace App\Sms;

/**
 * SMS segment counting (GSM-7 vs UCS-2), the same rule as smsSegments() in
 * resources/js/components/messaging/sms-text.ts. Used for the broadcast's cost estimate only.
 */
final class SmsSegments
{
    private const string GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?"
        .'¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

    private const string GSM_EXTENDED = "^{}\\[~]|€\f";

    public static function count(string $text): int
    {
        $basic = mb_str_split(self::GSM_BASIC);
        $extended = mb_str_split(self::GSM_EXTENDED);
        $gsm = true;
        $units = 0;
        foreach (mb_str_split($text) as $ch) {
            if (in_array($ch, $basic, true)) {
                $units++;
            } elseif (in_array($ch, $extended, true)) {
                $units += 2;
            } else {
                $gsm = false;
                break;
            }
        }
        if (! $gsm) {
            // UTF-16 code units, as JavaScript's String.length.
            $units = intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
        }
        if ($units === 0) {
            return 0;
        }
        [$single, $multi] = $gsm ? [160, 153] : [70, 67];

        return $units <= $single ? 1 : (int) ceil($units / $multi);
    }
}
