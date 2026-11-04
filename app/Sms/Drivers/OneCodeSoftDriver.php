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
 * OneCodeSoft Bulk SMS v3: POST {base_url}/v3/send-sms with a Bearer key and a JSON body of number,
 * message and an optional senderid. The base URL is account-specific (Dashboard > API), so there is no
 * default. Business outcomes come back as HTTP 200 with a body status of success or error: the body is
 * what counts, not the HTTP code. Only timeouts and 5xx are retryable; a provider rejection never is,
 * because a retry could be charged twice. The key is never put in a result message.
 */
final readonly class OneCodeSoftDriver implements SmsGateway
{
    public function __construct(
        private string $apiKey,
        private string $senderId,
        private string $baseUrl,
    ) {}

    public function send(SmsMessage $message): SmsResult
    {
        if ($this->apiKey === '' || trim($this->baseUrl) === '') {
            return SmsResult::failed('OneCodeSoft needs an API key and a base URL (Dashboard > API).');
        }

        // Accept a pasted ".../v3/" as well as the bare account URL.
        $base = rtrim(trim($this->baseUrl), '/');
        if (str_ends_with($base, '/v3')) {
            $base = substr($base, 0, -3);
        }

        $payload = ['number' => $message->msisdn(), 'message' => $message->body];
        if ($this->senderId !== '') {
            // Omitted when blank so the provider picks an approved non-masking sender.
            $payload['senderid'] = $this->senderId;
        }

        try {
            $response = Http::withToken($this->apiKey)->acceptJson()->asJson()->timeout(15)->post($base.'/v3/send-sms', $payload);
        } catch (ConnectionException $e) {
            return SmsResult::failed('Could not reach OneCodeSoft: '.$this->scrub($e->getMessage()), retryable: true);
        } catch (Throwable $e) {
            return SmsResult::failed($this->scrub($e->getMessage()));
        }

        if ($response->serverError()) {
            return SmsResult::failed('OneCodeSoft error (HTTP '.$response->status().').', retryable: true);
        }

        if ($response->status() === 401) {
            return SmsResult::failed('OneCodeSoft rejected the API key (HTTP 401). Check the key in Settings > SMS.');
        }

        $status = strtolower((string) $response->json('status', ''));
        $recipient = strtolower((string) $response->json('data.results.0.status', ''));
        if ($response->successful() && $status === 'success' && $recipient === 'sent') {
            $reference = $response->json('data.results.0.message_id');

            return SmsResult::sent(is_scalar($reference) && (string) $reference !== '' ? (string) $reference : $message->reference);
        }

        $error = $response->json('message');
        if (is_string($error) && $error !== '') {
            return SmsResult::failed($this->scrub($error));
        }
        if ($status === 'success' && $recipient !== '') {
            return SmsResult::failed('OneCodeSoft did not send the message (recipient status: '.$recipient.').');
        }

        return SmsResult::failed('OneCodeSoft rejected the message (HTTP '.$response->status().').');
    }

    /** Defence in depth: never let the key reach a stored result, whatever the provider or HTTP client echoes. */
    private function scrub(string $text): string
    {
        return str_replace($this->apiKey, '[key]', $text);
    }
}
