<?php

declare(strict_types=1);

namespace App\Enums;

enum MemberAuth: string
{
    case None = 'none';
    case PinOnline = 'pin_online';
    case PinOfflineOk = 'pin_offline_ok';
    case PinOfflineFailed = 'pin_offline_failed';
    case Override = 'override';
    case NoPinPolicy = 'no_pin_policy';
}
