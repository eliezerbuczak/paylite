<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidUserTypeException;
use App\Domain\ValueObject\UserType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UserType::class)]
class UserTypeTest extends TestCase
{
    public function test_creates_common_from_string(): void
    {
        self::assertSame(UserType::Common, UserType::fromString('common'));
    }

    public function test_creates_merchant_from_string(): void
    {
        self::assertSame(UserType::Merchant, UserType::fromString('merchant'));
    }

    public function test_rejects_unknown_type(): void
    {
        $this->expectException(InvalidUserTypeException::class);

        UserType::fromString('admin');
    }
}
