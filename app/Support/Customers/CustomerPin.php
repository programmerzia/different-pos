<?php

declare(strict_types=1);

namespace App\Support\Customers;

use App\Models\Customer;
use Carbon\CarbonInterface;

/**
 * Customer PIN rules (04_TRD §5.3): 4–6 digits, argon2id on the server only, a weak-PIN denylist,
 * five wrong tries lock the PIN for 30 minutes. Pure functions; the Actions do the writes.
 */
final class CustomerPin
{
    public const string PATTERN = '/^\d{4,6}$/';

    public const int MAX_FAILURES = 5;

    public const int LOCK_MINUTES = 30;

    public const string STATUS_SET = 'set';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_LOCKED = 'locked';

    /** Well-known PINs beyond the repeated / sequential patterns. */
    private const array DENYLIST = [
        '1212', '2580', '0852', '1004', '2000', '6969', '1122', '1313', '2001', '1010', '2112', '1221',
        '7410', '1478', '2468', '1357', '0007', '0070', '0101', '1230', '123123', '112233', '159753', '147258',
        '123321', '654321',
    ];

    public static function wellFormed(string $pin): bool
    {
        return preg_match(self::PATTERN, $pin) === 1;
    }

    /**
     * Too easy to guess: one repeated digit, ascending/descending runs (incl. 0 after 9 / 9 after 0),
     * a repeated 2- or 3-digit block, the denylist, or the tail of the customer's phone number.
     */
    public static function isWeak(string $pin, ?string $phone = null): bool
    {
        if (! self::wellFormed($pin)) {
            return true;
        }
        if (count(array_unique(str_split($pin))) === 1) {
            return true;
        }
        if (self::isRun($pin)) {
            return true;
        }
        if (self::isRepeatedBlock($pin)) {
            return true;
        }
        if (in_array($pin, self::DENYLIST, true)) {
            return true;
        }

        $digits = $phone === null ? '' : preg_replace('/\D+/', '', $phone) ?? '';

        return $digits !== '' && strlen($digits) >= strlen($pin) && str_ends_with($digits, $pin);
    }

    /** set | pending (no PIN yet) | locked (too many wrong tries, until pin_locked_until). */
    public static function status(Customer $customer, ?CarbonInterface $now = null): string
    {
        if (self::isLocked($customer, $now)) {
            return self::STATUS_LOCKED;
        }

        return $customer->pin_hash === null || $customer->pin_hash === '' ? self::STATUS_PENDING : self::STATUS_SET;
    }

    public static function isLocked(Customer $customer, ?CarbonInterface $now = null): bool
    {
        return $customer->pin_locked_until !== null && $customer->pin_locked_until->isAfter($now ?? now());
    }

    private static function isRun(string $pin): bool
    {
        $up = true;
        $down = true;
        for ($i = 1, $n = strlen($pin); $i < $n; $i++) {
            $prev = (int) $pin[$i - 1];
            $cur = (int) $pin[$i];
            $up = $up && $cur === ($prev + 1) % 10;
            $down = $down && $cur === ($prev + 9) % 10;
        }

        return $up || $down;
    }

    private static function isRepeatedBlock(string $pin): bool
    {
        $n = strlen($pin);
        foreach ([2, 3] as $size) {
            if ($n % $size === 0 && $n > $size && count(array_unique(str_split($pin, $size))) === 1) {
                return true;
            }
        }

        return false;
    }
}
