<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Wallet\Infrastructure\Persistence;

use App\User\Domain\Exception\UserNotFoundException;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Infrastructure\Persistence\WalletRepository;
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

    public function test_records_a_credit_ledger_entry_for_the_deposit(): void
    {
        $wallet = WalletFactory::withBalance(1000);

        $deposit = $this->repository()->deposit($wallet->user_id, Money::fromCents(5000));
        $entries = Db::table('ledger_entries')->where('wallet_id', $wallet->id);

        self::assertSame(1, $entries->count());
        self::assertSame('credit', $entries->value('direction'));
        self::assertSame(5000, (int) $entries->value('amount_cents'));
        self::assertSame(6000, (int) $entries->value('balance_after_cents'));
        self::assertSame('deposit', $entries->value('entry_type'));
        self::assertSame($deposit->id, (int) $entries->value('related_deposit_id'));
        self::assertNull($entries->value('related_transfer_id'));
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

    public function test_reads_the_current_balance(): void
    {
        $wallet = WalletFactory::withBalance(1234);

        self::assertSame(1234, $this->repository()->balanceOf($wallet->user_id)->cents);
    }

    public function test_balance_read_throws_user_not_found_for_unknown_user(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->repository()->balanceOf(999999);
    }

    private function repository(): WalletRepositoryInterface
    {
        return ApplicationContext::getContainer()->get(WalletRepositoryInterface::class);
    }
}
