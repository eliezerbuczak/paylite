<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidEmailException;
use App\Domain\ValueObject\Email;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Email::class)]
class EmailTest extends TestCase
{
    public function test_accepts_valid_email(): void
    {
        $email = Email::fromString('jane@example.com');

        self::assertSame('jane@example.com', $email->value);
    }

    public function test_normalizes_case_and_whitespace(): void
    {
        $email = Email::fromString('  Jane@Example.COM ');

        self::assertSame('jane@example.com', $email->value);
    }

    public function test_rejects_invalid_email(): void
    {
        $this->expectException(InvalidEmailException::class);

        Email::fromString('not-an-email');
    }

    public function test_rejects_empty_string(): void
    {
        $this->expectException(InvalidEmailException::class);

        Email::fromString('');
    }
}
