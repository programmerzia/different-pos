<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A sale date in the future, or in the past without pos.backdate or beyond the tenant's backdate_days. */
final class BackdateNotAllowed extends DomainException
{
    public static function future(): self
    {
        return new self('A sale cannot be dated in the future.');
    }

    public static function noPermission(): self
    {
        return new self('Back-dating a sale needs the back-date permission.');
    }

    public static function tooOld(int $days): self
    {
        return new self(sprintf('A sale can be back-dated by at most %d days.', $days));
    }
}
