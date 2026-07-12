<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service;

use App\Domain\Entity\Deposit;
use App\Domain\Exception\InvalidAmountException;
use App\Domain\Repository\WalletRepositoryInterface;
use App\Domain\ValueObject\Money;
use App\DTO\DepositMoneyInput;
use App\Service\DepositMoneyService;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DepositMoneyService::class)]
class DepositMoneyServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_deposits_positive_amount_into_user_wallet(): void
    {
        $expected = new Deposit(
            id: 1,
            walletId: 7,
            amount: Money::fromCents(5000),
            createdAt: new DateTimeImmutable('2026-07-12 12:00:00'),
        );
        $repository = Mockery::mock(WalletRepositoryInterface::class);
        $repository->shouldReceive('deposit')
            ->once()
            ->withArgs(fn (int $userId, Money $amount): bool => $userId === 4 && $amount->cents === 5000)
            ->andReturn($expected);

        $service = new DepositMoneyService($repository);
        $deposit = $service->execute(new DepositMoneyInput(userId: 4, amount: Money::fromDecimal(50.0)));

        self::assertSame($expected, $deposit);
    }

    public function test_rejects_zero_amount_without_touching_the_repository(): void
    {
        $repository = Mockery::mock(WalletRepositoryInterface::class);
        $repository->shouldNotReceive('deposit');

        $service = new DepositMoneyService($repository);

        $this->expectException(InvalidAmountException::class);

        $service->execute(new DepositMoneyInput(userId: 4, amount: Money::fromCents(0)));
    }

    public function test_rejects_negative_amount_without_touching_the_repository(): void
    {
        $repository = Mockery::mock(WalletRepositoryInterface::class);
        $repository->shouldNotReceive('deposit');

        $service = new DepositMoneyService($repository);

        $this->expectException(InvalidAmountException::class);

        $service->execute(new DepositMoneyInput(userId: 4, amount: Money::fromDecimal(-10.0)));
    }
}
