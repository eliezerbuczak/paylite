<?php

declare(strict_types=1);

namespace HyperfTest\Integration\TestSupport;

use HyperfTest\Factory\DepositFactory;
use HyperfTest\Factory\TransferFactory;
use HyperfTest\Factory\UserFactory;
use HyperfTest\Factory\WalletFactory;
use HyperfTest\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @internal
 */
#[CoversNothing]
class FactoriesTest extends IntegrationTestCase
{
    public function test_user_factory_persists_both_types(): void
    {
        $common = UserFactory::common();
        $merchant = UserFactory::merchant();

        self::assertSame('common', $common->refresh()->type);
        self::assertSame('merchant', $merchant->refresh()->type);
    }

    public function test_user_factory_accepts_overrides(): void
    {
        $user = UserFactory::common(['email' => 'fixed@example.com']);

        self::assertSame('fixed@example.com', $user->refresh()->email);
    }

    public function test_wallet_factory_persists_balance(): void
    {
        $wallet = WalletFactory::withBalance(12345);

        self::assertSame(12345, $wallet->refresh()->balance_cents);
    }

    public function test_transfer_factory_persists(): void
    {
        $transfer = TransferFactory::create(['amount_cents' => 777]);

        self::assertSame(777, $transfer->refresh()->amount_cents);
    }

    public function test_deposit_factory_persists(): void
    {
        $deposit = DepositFactory::create(['amount_cents' => 999]);

        self::assertSame(999, $deposit->refresh()->amount_cents);
    }
}
