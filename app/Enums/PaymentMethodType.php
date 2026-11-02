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
}
