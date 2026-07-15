<?php

declare(strict_types=1);

namespace HyperfTest\Unit\User\Domain\Entity;

use App\User\Domain\Entity\User;
use App\User\Domain\Exception\MerchantCannotTransferException;
use App\User\Domain\ValueObject\Document;
use App\User\Domain\ValueObject\Email;
use App\User\Domain\ValueObject\UserType;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(User::class)]
class UserTest extends TestCase
{
    public function test_common_user_can_transfer(): void
    {
        $this->user(UserType::Common)->assertCanTransfer();

        $this->addToAssertionCount(1);
    }

    public function test_merchant_cannot_transfer(): void
    {
        $this->expectException(MerchantCannotTransferException::class);

        $this->user(UserType::Merchant)->assertCanTransfer();
    }

    private function user(UserType $type): User
    {
        return new User(
            id: 4,
            fullName: 'Jane Doe',
            document: Document::fromString('52998224725'),
            email: Email::fromString('user4@example.com'),
            type: $type,
            createdAt: new DateTimeImmutable('2026-07-12 10:00:00'),
        );
    }
}
