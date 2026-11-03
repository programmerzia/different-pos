<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A stock transfer that breaks a business rule. */
final class InvalidStockTransfer extends DomainException
{
    public static function noLines(): self
    {
        return new self('The transfer has no lines.');
    }

    public static function sameBranch(): self
    {
        return new self('The origin and destination branches cannot be the same.');
    }

    public static function inactiveBranch(): self
    {
        return new self('The destination branch is not active.');
    }

    public static function nonPositiveQuantity(int $line): self
    {
        return new self(sprintf('Line %d: the quantity must be greater than zero.', $line));
    }

    public static function untracked(int $line): self
    {
        return new self(sprintf('Line %d: the product does not track stock.', $line));
    }

    public static function duplicateProduct(int $line): self
    {
        return new self(sprintf('Line %d: the product appears more than once.', $line));
    }

    public static function notInTransit(string $number): self
    {
        return new self(sprintf('Transfer %s is not in transit.', $number));
    }

    public static function wrongBranch(string $number): self
    {
        return new self(sprintf('Transfer %s must be received at its destination branch.', $number));
    }

    public static function overReceived(int $line): self
    {
        return new self(sprintf('Line %d: the received quantity is more than the quantity sent.', $line));
    }

    public static function shortReasonRequired(int $line): self
    {
        return new self(sprintf('Line %d: a reason is needed when less than the sent quantity is received.', $line));
    }

    public static function unknownLine(int $id): self
    {
        return new self(sprintf('Line %d does not belong to this transfer.', $id));
    }
}
