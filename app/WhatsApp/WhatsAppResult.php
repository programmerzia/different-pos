<?php

declare(strict_types=1);

namespace App\WhatsApp;

use App\Enums\MessageStatus;

final readonly class WhatsAppResult
{
    public bool $ok;

    private function __construct(
        public MessageStatus $status,
        public ?string $providerRef,
        public ?string $error,
        /** A timeout or a 5xx / 429: worth another attempt. A provider rejection is not. */
        public bool $retryable,
        /** Click-to-chat: the wa.me deep link the UI opens in a new tab. */
        public ?string $link = null,
    ) {
        $this->ok = $status !== MessageStatus::Failed;
    }

    public static function sent(?string $providerRef = null): self
    {
        return new self(MessageStatus::Sent, $providerRef, null, false);
    }

    /** The text was handed to the user's own WhatsApp through a wa.me link; delivery is not known. */
    public static function handoff(string $link): self
    {
        return new self(MessageStatus::Handoff, 'wa.me', null, false, $link);
    }

    public static function failed(string $error, bool $retryable = false): self
    {
        return new self(MessageStatus::Failed, null, mb_substr($error, 0, 500), $retryable);
    }
}
