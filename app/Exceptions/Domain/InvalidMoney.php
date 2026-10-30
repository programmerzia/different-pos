<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class InvalidMoney extends DomainException
{
    public static function unparsable(string $input): self
    {
        return new self(sprintf('"%s" is not a valid amount.', $input));
    }

    public static function tooPrecise(string $input): self
    {
        return new self(sprintf('"%s" has more than two decimal places.', $input));
    }

    public static function outOfRange(string $input): self
    {
        return new self(sprintf('"%s" is out of range.', $input));
    }

    public static function badWeights(string $reason): self
    {
        return new self('Cannot allocate money: '.$reason.'.');
    }
}
