<?php

declare(strict_types=1);

namespace App\Legacy\Transform;

use Normalizer;

/**
 * Legacy text arrives as raw bytes (selected as BINARY): latin1 columns hold UTF-8 bytes (ADR 0002 M3).
 * Decode as strict UTF-8, fall back to Windows-1252, then trim and NFC-normalise.
 */
final class Text
{
    /** Values the legacy UI stored instead of "nothing". */
    private const array PLACEHOLDERS = ['null@gmail.com', '01717', 'no description', 'na', 'n/a', '-'];

    /** @return array{0: string|null, 1: bool} [text or null when blank, whether the 1252 fallback was used] */
    public static function decode(mixed $bytes): array
    {
        if ($bytes === null || ! is_scalar($bytes)) {
            return [null, false];
        }

        $value = (string) $bytes;
        $fallback = false;

        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            $fallback = true;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }

        return [$value === '' ? null : $value, $fallback];
    }

    /** Null for blanks and legacy placeholder values. */
    public static function orNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return in_array(mb_strtolower(trim($value)), self::PLACEHOLDERS, true) ? null : $value;
    }

    /** Dedupe key for names: trimmed, case-insensitive. */
    public static function key(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    public static function limit(?string $value, int $length): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $length);
    }

    public static function email(?string $value): ?string
    {
        $value = self::orNull($value);

        return $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? mb_strtolower($value) : null;
    }
}
