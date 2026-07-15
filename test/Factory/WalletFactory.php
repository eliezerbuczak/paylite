<?php

declare(strict_types=1);

namespace HyperfTest\Factory;

use App\Model\Wallet;
use App\User\Infrastructure\Model\User;

final class WalletFactory
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function forUser(User $user, array $overrides = []): Wallet
    {
        $wallet = new Wallet();
        $wallet->fill(array_merge(['user_id' => $user->id], $overrides));
        $wallet->save();

        return $wallet;
    }

    /**
     * Creates a common user together with a wallet holding the given balance.
     *
     * @param array<string, mixed> $overrides
     */
    public static function withBalance(int $balanceCents, array $overrides = []): Wallet
    {
        return self::forUser(UserFactory::common(), array_merge([
            'balance_cents' => $balanceCents,
        ], $overrides));
    }
}
