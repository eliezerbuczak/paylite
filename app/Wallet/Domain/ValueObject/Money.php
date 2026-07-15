<?php

declare(strict_types=1);

namespace App\Wallet\Domain\ValueObject;

use App\Wallet\Domain\Exception\InvalidAmountException;

/**
 * Monetary amount in integer cents (BRL, fixed scale 2). Never floats.
 */
final readonly class Money
{
    private const SCALE = 100;

    /**
     * Keeps every accepted value far below 2^53 cents, where doubles stop
     * representing integers exactly (and the int cast could overflow).
     */
    private const MAX_CENTS = 10_000_000_000_000;

    /**
     * Representation noise of a valid 2-decimal value is ~1e-13 cents;
     * a genuine third decimal place is >= 0.1 cent. Anything in between
     * is excess precision, not noise.
     */
    private const PRECISION_TOLERANCE = 0.01;

    private function __construct(public int $cents)
    {
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * round(), never a cast: (int) (19.99 * 100) truncates to 1998 because of
     * binary float representation. Values with more than 2 decimal places are
     * rejected instead of silently rounded.
     */
    public static function fromDecimal(float $value): self
    {
        $cents = round($value * self::SCALE);

        if (abs($value * self::SCALE - $cents) > self::PRECISION_TOLERANCE) {
            throw InvalidAmountException::exceedsPrecision();
        }

        if (abs($cents) > self::MAX_CENTS) {
            throw InvalidAmountException::tooLarge();
        }

        return new self((int) $cents);
    }

    public function toDecimal(): float
    {
        return $this->cents / self::SCALE;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function isLessThan(self $other): bool
    {
        return $this->cents < $other->cents;
    }
}
