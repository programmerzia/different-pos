<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * tenants.settings.customer_pin_policy: what the register does when a member is attached.
 * off = never ask (sales get member_auth no_pin_policy); optional = ask members who have a PIN,
 * skipping is allowed (member_auth none); required = every member sale needs the PIN (pin_online)
 * or a manager override (override).
 */
enum CustomerPinPolicy: string
{
    case Off = 'off';
    case Optional = 'optional';
    case Required = 'required';

    /** The stored setting, with the pre-2.12 values (`always`, `credit_only`) mapped. */
    public static function fromSetting(mixed $value): self
    {
        return match ($value) {
            'off' => self::Off,
            'required', 'always' => self::Required,
            default => self::Optional,
        };
    }
}
