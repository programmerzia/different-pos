<?php

declare(strict_types=1);

namespace App\Support\Quantity;

use App\Exceptions\Domain\InvalidQuantity;
use App\Support\Numbers\Digits;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use JsonSerializable;

/**
 * A quantity stored as DECIMAL(14,3) and handled as integer milli-units (1.250 kg = 1250). 04_TRD §3.4.
 * The TypeScript twin is resources/js/engine/quantity.ts.
 */
final readonly class Quantity implements JsonSerializable
{
    public const int SCALE_FACTOR = 1000;

    /** DECIMAL(14,3) holds at most 11 integer digits. */
    public const int MAX_MILLI = 99_999_999_999_999;

    private function __construct(public int $milli) {}

    public static function ofMilli(int $milli): self
    {
        if (abs($milli) > self::MAX_MILLI) {
            throw InvalidQuantity::outOfRange((string) $milli);
        }

        return new self($milli);
    }

    public static function ofUnits(int $units): self
    {
        return self::ofMilli($units * self::SCALE_FACTOR);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /** Parses "1.25", "2", "১.৫". More than three decimal places is rejected, never rounded. */
    public static function parse(string $input): self
    {
        $clean = str_replace([',', ' '], '', Digits::toLatin(trim($input)));

        if (preg_match('/^-?\d+(\.\d+)?$/', $clean) !== 1) {
            throw InvalidQuantity::unparsable($input);
        }

        try {
            $decimal = BigDecimal::of($clean)->strippedOfTrailingZeros();
        } catch (MathException) {
            throw InvalidQuantity::unparsable($input);
        }

        if ($decimal->getScale() > 3) {
            throw InvalidQuantity::tooPrecise($input);
        }

        try {
            return self::ofMilli($decimal->toScale(3)->getUnscaledValue()->toInt());
        } catch (MathException) {
            throw InvalidQuantity::outOfRange($input);
        }
    }

    public function plus(self $other): self
    {
        return self::ofMilli($this->milli + $other->milli);
    }

    public function minus(self $other): self
    {
        return self::ofMilli($this->milli - $other->milli);
    }

    public function negated(): self
    {
        return new self(-$this->milli);
    }

    public function isWhole(): bool
    {
        return $this->milli % self::SCALE_FACTOR === 0;
    }

    public function isZero(): bool
    {
        return $this->milli === 0;
    }

    public function isPositive(): bool
    {
        return $this->milli > 0;
    }

    public function isNegative(): bool
    {
        return $this->milli < 0;
    }

    public function equals(self $other): bool
    {
        return $this->milli === $other->milli;
    }

    public function compareTo(self $other): int
    {
        return $this->milli <=> $other->milli;
    }

    /** "1.250": the exact DECIMAL(14,3) column value. */
    public function toDecimalString(): string
    {
        return BigDecimal::ofUnscaledValue($this->milli, 3)->__toString();
    }

    /** Display: trailing zeros trimmed ("1.25", "2"); Bangla digits for "bn". */
    public function format(string $locale = 'en'): string
    {
        $text = BigDecimal::ofUnscaledValue($this->milli, 3)->strippedOfTrailingZeros()->__toString();

        return $locale === 'bn' ? Digits::toBangla($text) : $text;
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimalString();
    }
}
