<?php

declare(strict_types=1);

namespace App\Sms\Drivers;

use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsMessage;
use App\Sms\SmsResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * SSL Wireless single-SMS JSON API: POST {base_url}/api/v3/send-sms with api_token, sid, msisdn,
 * sms and csms_id. A 200 with status SUCCESS is a send; a FAILED status is a rejection (not retried);
 * timeouts and 5xx are retryable.
 */
final readonly class SslWirelessDriver implements SmsGateway
{
    public const string DEFAULT_BASE_URL = 'https://smsplus.sslwireless.com';

    public function __construct(
        private string $apiToken,
        private string $sid,
        private string $baseUrl = '',
    ) {}

    public function send(SmsMessage $message): SmsResult
    {
        if ($this->apiToken === '' || $this->sid === '') {
            return SmsResult::failed('SSL Wireless needs an API token and a sender ID (SID).');
        }

        $url = rtrim($this->baseUrl !== '' ? $this->baseUrl : self::DEFAULT_BASE_URL, '/').'/api/v3/send-sms';

        try {
            $response = Http::acceptJson()->asJson()->timeout(15)->post($url, [
                'api_token' => $this->apiToken,
                'sid' => $this->sid,
                'msisdn' => $message->msisdn(),
                'sms' => $message->body,
                'csms_id' => $message->reference,
            ]);
        } catch (ConnectionException $e) {
            return SmsResult::failed('Could not reach SSL Wireless: '.$e->getMessage(), retryable: true);
        } catch (Throwable $e) {
            return SmsResult::failed($e->getMessage());
        }

        if ($response->serverError()) {
            return SmsResult::failed('SSL Wireless error (HTTP '.$response->status().').', retryable: true);
        }

        $status = strtoupper((string) $response->json('status', ''));
        if ($response->successful() && $status === 'SUCCESS') {
            $reference = $response->json('smsinfo.0.reference_id') ?? $response->json('smsinfo.0.csms_id') ?? $message->reference;

            return SmsResult::sent(is_scalar($reference) ? (string) $reference : $message->reference);
        }

        $error = $response->json('error_message') ?? $response->json('smsinfo.0.status_message') ?? $response->json('message');

        return SmsResult::failed(is_string($error) && $error !== '' ? $error : 'SSL Wireless rejected the message (HTTP '.$response->status().').');
    }
}
