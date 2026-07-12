<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Monetary amount in integer cents (BRL, fixed scale 2). Never floats.
 */
final readonly class Money
{
    private const SCALE = 100;

    private function __construct(public int $cents)
    {
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * round(), never a cast: (int) (19.99 * 100) truncates to 1998 because of
     * binary float representation.
     */
    public static function fromDecimal(float $value): self
    {
        return new self((int) round($value * self::SCALE));
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
}
