<?php

declare(strict_types=1);

namespace App\Wallet\Application\Provisioning;

interface WalletProvisionerInterface
{
    /**
     * Creates a zero-balance wallet for a newly registered user. Expected
     * to run inside the caller's transaction, so a failure here rolls
     * back the user creation with it.
     */
    public function provisionForUser(int $userId): void;
}
