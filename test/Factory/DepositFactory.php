<?php

declare(strict_types=1);

namespace HyperfTest\Factory;

use App\Wallet\Infrastructure\Model\Deposit;

final class DepositFactory
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function create(array $overrides = []): Deposit
    {
        $deposit = new Deposit();
        $deposit->fill(array_merge([
            'wallet_id' => WalletFactory::withBalance(0)->id,
            'amount_cents' => 5000,
        ], $overrides));
        $deposit->save();

        return $deposit;
    }
}
