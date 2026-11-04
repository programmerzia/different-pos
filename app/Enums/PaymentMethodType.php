<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethodType: string
{
    case Cash = 'cash';
    case Card = 'card';
    case MobileWallet = 'mobile_wallet';
    case Bank = 'bank';
    case CustomerCredit = 'customer_credit';
    case ExchangeCredit = 'exchange_credit';

    /** Customer-side store credit: money never leaves the till, so it cannot pay a vendor. */
    public function isStoreCredit(): bool
    {
        return $this === self::CustomerCredit || $this === self::ExchangeCredit;
    }

    /** @return list<string> */
    public static function storeCreditValues(): array
    {
        return [self::CustomerCredit->value, self::ExchangeCredit->value];
    }
}
