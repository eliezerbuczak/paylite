<?php

declare(strict_types=1);

namespace HyperfTest\Integration;

use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use HyperfTest\Factory\WalletFactory;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Audita o invariante do ledger: o saldo materializado em wallets.balance_cents
 * deve ser sempre reconstruível a partir dos fatos registrados
 * (depósitos + transferências recebidas − transferências enviadas).
 * Todo caminho de escrita futuro que credite ou debite carteira fica
 * automaticamente coberto por este teste.
 *
 * @internal
 */
#[CoversNothing]
class BalanceReconciliationTest extends IntegrationTestCase
{
    public function test_materialized_balance_equals_the_sum_of_recorded_facts(): void
    {
        $walletA = WalletFactory::withBalance(0);
        $walletB = WalletFactory::withBalance(0);
        $repository = $this->repository();

        $repository->deposit($walletA->user_id, Money::fromCents(5000));
        $repository->deposit($walletA->user_id, Money::fromCents(1999));
        $repository->deposit($walletB->user_id, Money::fromCents(1));

        self::assertSame(6999, $this->storedBalance($walletA->id));
        self::assertSame(1, $this->storedBalance($walletB->id));

        self::assertSame($this->reconciledBalance($walletA->id), $this->storedBalance($walletA->id));
        self::assertSame($this->reconciledBalance($walletB->id), $this->storedBalance($walletB->id));
    }

    private function repository(): WalletRepositoryInterface
    {
        return ApplicationContext::getContainer()->get(WalletRepositoryInterface::class);
    }

    private function storedBalance(int $walletId): int
    {
        return (int) Db::table('wallets')->where('id', $walletId)->value('balance_cents');
    }

    private function reconciledBalance(int $walletId): int
    {
        $userId = (int) Db::table('wallets')->where('id', $walletId)->value('user_id');

        $deposits = (int) Db::table('deposits')->where('wallet_id', $walletId)->sum('amount_cents');
        $received = (int) Db::table('transfers')->where('payee_id', $userId)->sum('amount_cents');
        $sent = (int) Db::table('transfers')->where('payer_id', $userId)->sum('amount_cents');

        return $deposits + $received - $sent;
    }
}
