<?php

declare(strict_types=1);

namespace App\Sms\Drivers;

use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsMessage;
use App\Sms\SmsResult;
use Illuminate\Support\Facades\Log;

/** Development driver: writes the SMS to the application log. The body only goes in at debug level. */
final class LogDriver implements SmsGateway
{
    public function send(SmsMessage $message): SmsResult
    {
        Log::info('SMS (log driver)', ['to' => $message->phone, 'reference' => $message->reference, 'length' => mb_strlen($message->body)]);
        Log::debug('SMS body (log driver)', ['body' => $message->body]);

        return SmsResult::sent('log-'.$message->reference);
    }
}
