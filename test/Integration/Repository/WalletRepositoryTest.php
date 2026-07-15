<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Repository;

use App\Domain\Exception\InsufficientBalanceException;
use App\Domain\Repository\WalletRepositoryInterface;
use App\Domain\ValueObject\Money;
use App\Repository\WalletRepository;
use App\User\Domain\Exception\UserNotFoundException;
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

    public function test_transfers_between_wallets_and_records_the_fact(): void
    {
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::withBalance(500);

        $transfer = $this->repository()->transfer($payer->user_id, $payee->user_id, Money::fromCents(7500));

        self::assertGreaterThan(0, $transfer->id);
        self::assertSame($payer->user_id, $transfer->payerId);
        self::assertSame($payee->user_id, $transfer->payeeId);
        self::assertSame(7500, $transfer->amount->cents);

        self::assertSame(
            2500,
            (int) Db::table('wallets')->where('id', $payer->id)->value('balance_cents')
        );
        self::assertSame(
            8000,
            (int) Db::table('wallets')->where('id', $payee->id)->value('balance_cents')
        );
        self::assertSame(
            7500,
            (int) Db::table('transfers')->where('id', $transfer->id)->value('amount_cents')
        );
    }

    public function test_transfers_the_entire_balance(): void
    {
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::withBalance(0);

        $this->repository()->transfer($payer->user_id, $payee->user_id, Money::fromCents(10000));

        self::assertSame(
            0,
            (int) Db::table('wallets')->where('id', $payer->id)->value('balance_cents')
        );
        self::assertSame(
            10000,
            (int) Db::table('wallets')->where('id', $payee->id)->value('balance_cents')
        );
    }

    public function test_rejects_transfer_when_locked_balance_is_insufficient(): void
    {
        $payer = WalletFactory::withBalance(100);
        $payee = WalletFactory::withBalance(0);

        try {
            $this->repository()->transfer($payer->user_id, $payee->user_id, Money::fromCents(101));
            self::fail('Expected InsufficientBalanceException.');
        } catch (InsufficientBalanceException) {
        }

        self::assertSame(
            100,
            (int) Db::table('wallets')->where('id', $payer->id)->value('balance_cents')
        );
        self::assertSame(
            0,
            (int) Db::table('wallets')->where('id', $payee->id)->value('balance_cents')
        );
        self::assertSame(0, (int) Db::table('transfers')->count());
    }

    public function test_transfer_throws_user_not_found_when_payee_wallet_is_missing(): void
    {
        $payer = WalletFactory::withBalance(10000);

        try {
            $this->repository()->transfer($payer->user_id, 999999, Money::fromCents(100));
            self::fail('Expected UserNotFoundException.');
        } catch (UserNotFoundException) {
        }

        self::assertSame(
            10000,
            (int) Db::table('wallets')->where('id', $payer->id)->value('balance_cents')
        );
        self::assertSame(0, (int) Db::table('transfers')->count());
    }

    public function test_transfer_throws_user_not_found_when_payer_wallet_is_missing(): void
    {
        $payee = WalletFactory::withBalance(0);

        $this->expectException(UserNotFoundException::class);

        $this->repository()->transfer(999999, $payee->user_id, Money::fromCents(100));
    }

    private function repository(): WalletRepositoryInterface
    {
        return ApplicationContext::getContainer()->get(WalletRepositoryInterface::class);
    }
}
