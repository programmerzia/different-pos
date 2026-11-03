<?php

declare(strict_types=1);

namespace App\WhatsApp\Drivers;

use App\WhatsApp\Contracts\WhatsAppGateway;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp Business Cloud API (Meta Graph): POST {base}/{version}/{phone_number_id}/messages with a Bearer
 * access token. Without a template name the message goes as free-form `text` (allowed inside the 24-hour
 * customer-service window). With an approved template name it goes as a `template` message whose single body
 * parameter {{1}} is the whole text (the usual "catch-all" utility template), which Meta accepts outside the
 * window. A 2xx with messages[0].id is a send; a 4xx is a rejection (not retried, except 429); timeouts,
 * 429 and 5xx are retryable.
 */
final readonly class MetaCloudApiGateway implements WhatsAppGateway
{
    public const string DEFAULT_BASE_URL = 'https://graph.facebook.com';

    public const string API_VERSION = 'v21.0';

    public function __construct(
        private string $phoneNumberId,
        private string $accessToken,
        private string $templateName = '',
        private string $templateLanguage = 'bn',
        private string $baseUrl = self::DEFAULT_BASE_URL,
    ) {}

    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        if ($this->phoneNumberId === '' || $this->accessToken === '') {
            return WhatsAppResult::failed('The WhatsApp Cloud API needs a phone number ID and an access token.');
        }

        $url = rtrim($this->baseUrl, '/').'/'.self::API_VERSION.'/'.$this->phoneNumberId.'/messages';

        try {
            $response = Http::withToken($this->accessToken)->acceptJson()->asJson()->timeout(15)->post($url, $this->payload($message));
        } catch (ConnectionException $e) {
            return WhatsAppResult::failed('Could not reach the WhatsApp Cloud API: '.$e->getMessage(), retryable: true);
        } catch (Throwable $e) {
            return WhatsAppResult::failed($e->getMessage());
        }

        if ($response->serverError() || $response->status() === 429) {
            return WhatsAppResult::failed('WhatsApp Cloud API error (HTTP '.$response->status().').', retryable: true);
        }

        $id = $response->json('messages.0.id');
        if ($response->successful() && is_string($id) && $id !== '') {
            return WhatsAppResult::sent($id);
        }

        $error = $response->json('error.message');
        $code = $response->json('error.code');

        return WhatsAppResult::failed(is_string($error) && $error !== ''
            ? $error.(is_scalar($code) ? ' (code '.$code.')' : '')
            : 'The WhatsApp Cloud API rejected the message (HTTP '.$response->status().').');
    }

    /** @return array<string, mixed> */
    public function payload(WhatsAppMessage $message): array
    {
        $base = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $message->digits()];

        if ($this->templateName === '') {
            return [...$base, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $message->text]];
        }

        return [...$base, 'type' => 'template', 'template' => [
            'name' => $this->templateName,
            'language' => ['code' => $this->templateLanguage],
            'components' => [[
                'type' => 'body',
                'parameters' => [['type' => 'text', 'text' => $message->text]],
            ]],
        ]];
    }
}
