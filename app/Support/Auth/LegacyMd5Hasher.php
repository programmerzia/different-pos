<?php

declare(strict_types=1);

namespace App\Support\Auth;

/** Verifies a password against the unsalted MD5 digest carried over from the legacy system. */
final class LegacyMd5Hasher
{
    public function check(string $plain, ?string $digest): bool
    {
        if ($digest === null || strlen($digest) !== 32) {
            return false;
        }

        return hash_equals(strtolower($digest), md5($plain));
    }
}
