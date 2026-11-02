<?php

declare(strict_types=1);

namespace App\Sms\Drivers;

use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsMessage;
use App\Sms\SmsResult;

/** No provider configured: nothing is sent, and the log says so instead of pretending. */
final class NullDriver implements SmsGateway
{
    public function send(SmsMessage $message): SmsResult
    {
        return SmsResult::failed('No SMS provider is configured.');
    }
}
