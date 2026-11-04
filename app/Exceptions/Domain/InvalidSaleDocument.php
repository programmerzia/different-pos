<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A sale, return, exchange or customer payment that breaks a structural rule. */
final class InvalidSaleDocument extends DomainException
{
    public static function noLines(): self
    {
        return new self('The document has no lines.');
    }

    public static function nonPositiveQuantity(int $line): self
    {
        return new self(sprintf('Line %d: the quantity must be greater than zero.', $line));
    }

    public static function negativeAmount(string $field): self
    {
        return new self(sprintf('%s must not be negative.', $field));
    }

    public static function notCompleted(string $number): self
    {
        return new self(sprintf('Sale %s is not a completed sale.', $number));
    }

    public static function notVoidable(string $number, string $reason): self
    {
        return new self(sprintf('Sale %s cannot be voided: %s.', $number, $reason));
    }

    public static function itemNotInSale(int $saleItemId, string $number): self
    {
        return new self(sprintf('Line %d does not belong to sale %s.', $saleItemId, $number));
    }

    public static function wrongCustomer(string $number): self
    {
        return new self(sprintf('Sale %s belongs to another customer.', $number));
    }

    public static function overpaid(string $number): self
    {
        return new self(sprintf('The allocation is larger than the due of sale %s.', $number));
    }

    public static function allocationsExceedPayment(): self
    {
        return new self('The allocations add up to more than the payment.');
    }

    public static function nonPositivePayment(): self
    {
        return new self('The payment amount must be greater than zero.');
    }

    public static function walkInAccount(): self
    {
        return new self('The walk-in customer has no account.');
    }

    public static function noWalkInCustomer(): self
    {
        return new self('The business has no walk-in customer.');
    }

    public static function missingSystemMethod(string $type): self
    {
        $label = __('app.method_types.'.$type);

        return new self((string) __('app.system_method_missing', ['method' => is_string($label) ? $label : $type]));
    }

    public static function changed(string $number): self
    {
        return new self(sprintf('Sale %s changed meanwhile. Please try again.', $number));
    }

    public static function exchangeOnly(): self
    {
        return new self('An exchange return is made through the exchange flow.');
    }

    public static function duplicateLine(int $saleItemId): self
    {
        return new self(sprintf('Sale line %d is listed twice.', $saleItemId));
    }

    public static function exchangeCustomer(): self
    {
        return new self('The replacement sale of an exchange must be for the customer of the original sale.');
    }

    public static function rawMaterial(string $name): self
    {
        return new self(sprintf('"%s" is a raw material and cannot be sold.', $name));
    }
}
