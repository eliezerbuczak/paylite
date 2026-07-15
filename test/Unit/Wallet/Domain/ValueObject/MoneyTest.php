<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Wallet\Domain\ValueObject;

use App\Wallet\Domain\Exception\InvalidAmountException;
use App\Wallet\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Money::class)]
class MoneyTest extends TestCase
{
    public function test_creates_from_cents(): void
    {
        $money = Money::fromCents(1999);

        self::assertSame(1999, $money->cents);
    }

    public function test_creates_from_decimal_value(): void
    {
        $money = Money::fromDecimal(50.0);

        self::assertSame(5000, $money->cents);
    }

    public function test_rounds_decimal_instead_of_truncating(): void
    {
        $money = Money::fromDecimal(19.99);

        self::assertSame(1999, $money->cents);
    }

    public function test_converts_back_to_decimal(): void
    {
        $money = Money::fromCents(1999);

        self::assertSame(19.99, $money->toDecimal());
    }

    public function test_positive_amount_is_positive(): void
    {
        self::assertTrue(Money::fromCents(1)->isPositive());
    }

    public function test_zero_is_not_positive(): void
    {
        self::assertFalse(Money::fromCents(0)->isPositive());
    }

    public function test_negative_amount_is_not_positive(): void
    {
        self::assertFalse(Money::fromDecimal(-10.0)->isPositive());
    }

    public function test_rejects_more_than_two_decimal_places_instead_of_rounding(): void
    {
        $this->expectException(InvalidAmountException::class);

        Money::fromDecimal(10.001);
    }

    public function test_rejects_half_cents_instead_of_rounding_up(): void
    {
        $this->expectException(InvalidAmountException::class);

        Money::fromDecimal(10.005);
    }

    public function test_rejects_amounts_beyond_the_maximum(): void
    {
        $this->expectException(InvalidAmountException::class);

        Money::fromDecimal(1e15);
    }

    public function test_accepts_the_maximum_amount(): void
    {
        $money = Money::fromDecimal(100_000_000_000.0);

        self::assertSame(10_000_000_000_000, $money->cents);
    }

    public function test_equal_amounts_are_equal(): void
    {
        self::assertTrue(Money::fromCents(1999)->equals(Money::fromDecimal(19.99)));
        self::assertFalse(Money::fromCents(1999)->equals(Money::fromCents(2000)));
    }
}
