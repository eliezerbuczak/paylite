<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Deposit;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\ValueObject\Money;

interface WalletRepositoryInterface
{
    /**
     * Credits the user's wallet and records the deposit, atomically.
     *
     * @throws UserNotFoundException when the user (and thus their wallet) does not exist
     */
    public function deposit(int $userId, Money $amount): Deposit;
}
