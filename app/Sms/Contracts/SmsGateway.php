<?php

declare(strict_types=1);

namespace App\Sms\Contracts;

use App\Sms\SmsMessage;
use App\Sms\SmsResult;

/** The only way application code sends an SMS. Drivers never throw: a failure is a SmsResult. */
interface SmsGateway
{
    public function send(SmsMessage $message): SmsResult;
}
