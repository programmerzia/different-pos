<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A catalog write (category, brand, unit, tax, product, family) that breaks a business rule. */
final class InvalidCatalogData extends DomainException
{
    public static function required(string $field): self
    {
        return new self(sprintf('%s is required.', $field));
    }

    public static function duplicate(string $what, string $value): self
    {
        return new self(sprintf('A %s "%s" already exists.', $what, $value));
    }

    public static function invalid(string $message): self
    {
        return new self($message);
    }

    public static function reservedBarcode(): self
    {
        return new self('Barcodes starting with MC are reserved for membership cards.');
    }
}
