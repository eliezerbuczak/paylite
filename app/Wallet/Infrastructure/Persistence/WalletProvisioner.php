<?php

declare(strict_types=1);

namespace App\Wallet\Infrastructure\Persistence;

use App\Wallet\Application\Provisioning\WalletProvisionerInterface;
use App\Wallet\Infrastructure\Model\Wallet as WalletModel;

final class WalletProvisioner implements WalletProvisionerInterface
{
    public function provisionForUser(int $userId): void
    {
        $wallet = new WalletModel();
        $wallet->fill(['user_id' => $userId]);
        $wallet->save();
    }
}
