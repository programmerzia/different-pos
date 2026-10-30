<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class InvalidQuantity extends DomainException
{
    public static function unparsable(string $input): self
    {
        return new self(sprintf('"%s" is not a valid quantity.', $input));
    }

    public static function tooPrecise(string $input): self
    {
        return new self(sprintf('"%s" has more than three decimal places.', $input));
    }

    public static function outOfRange(string $input): self
    {
        return new self(sprintf('"%s" is out of range.', $input));
    }
}
