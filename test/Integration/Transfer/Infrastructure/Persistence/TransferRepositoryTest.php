<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Transfer\Infrastructure\Persistence;

use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Infrastructure\Persistence\TransferRepository;
use App\User\Domain\Exception\UserNotFoundException;
use App\Wallet\Domain\Exception\InsufficientBalanceException;
use App\Wallet\Domain\ValueObject\Money;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use HyperfTest\Factory\WalletFactory;
use HyperfTest\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(TransferRepository::class)]
class TransferRepositoryTest extends IntegrationTestCase
{
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

    private function repository(): TransferRepositoryInterface
    {
        return ApplicationContext::getContainer()->get(TransferRepositoryInterface::class);
    }
}
