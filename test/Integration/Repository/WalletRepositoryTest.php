<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Repository;

use App\Domain\Exception\UserNotFoundException;
use App\Domain\Repository\WalletRepositoryInterface;
use App\Domain\ValueObject\Money;
use App\Repository\WalletRepository;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use HyperfTest\Factory\WalletFactory;
use HyperfTest\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(WalletRepository::class)]
class WalletRepositoryTest extends IntegrationTestCase
{
    public function test_credits_wallet_and_records_the_deposit(): void
    {
        $wallet = WalletFactory::withBalance(1000);

        $deposit = $this->repository()->deposit($wallet->user_id, Money::fromCents(5000));

        self::assertGreaterThan(0, $deposit->id);
        self::assertSame($wallet->id, $deposit->walletId);
        self::assertSame(5000, $deposit->amount->cents);

        self::assertSame(
            6000,
            (int) Db::table('wallets')->where('id', $wallet->id)->value('balance_cents')
        );
        self::assertSame(
            5000,
            (int) Db::table('deposits')->where('id', $deposit->id)->value('amount_cents')
        );
    }

    public function test_accumulates_balance_across_deposits(): void
    {
        $wallet = WalletFactory::withBalance(0);
        $repository = $this->repository();

        $repository->deposit($wallet->user_id, Money::fromCents(1999));
        $repository->deposit($wallet->user_id, Money::fromCents(1));

        self::assertSame(
            2000,
            (int) Db::table('wallets')->where('id', $wallet->id)->value('balance_cents')
        );
    }

    public function test_throws_user_not_found_for_unknown_user(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->repository()->deposit(999999, Money::fromCents(100));
    }

    private function repository(): WalletRepositoryInterface
    {
        return ApplicationContext::getContainer()->get(WalletRepositoryInterface::class);
    }
}
