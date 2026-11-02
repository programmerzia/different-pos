<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A user, role, branch, register or business-settings write that breaks a rule. */
final class InvalidAdminData extends DomainException
{
    public static function invalid(string $message): self
    {
        return new self($message);
    }

    public static function required(string $field): self
    {
        return new self(sprintf('%s is required.', $field));
    }

    public static function duplicate(string $what, string $value): self
    {
        return new self(sprintf('A %s "%s" already exists.', $what, $value));
    }
}
