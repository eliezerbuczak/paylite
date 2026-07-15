<?php

declare(strict_types=1);

namespace App\Wallet\Domain\Repository;

use App\User\Domain\Exception\UserNotFoundException;
use App\Wallet\Domain\Entity\Deposit;
use App\Wallet\Domain\ValueObject\Money;

interface WalletRepositoryInterface
{
    /**
     * Credits the user's wallet and records the deposit, atomically.
     *
     * @throws UserNotFoundException when the user (and thus their wallet) does not exist
     */
    public function deposit(int $userId, Money $amount): Deposit;

    /**
     * Current balance, read without any lock (cheap pre-check only —
     * the authoritative re-check happens in the transfer repository,
     * under lock).
     *
     * @throws UserNotFoundException when the user (and thus their wallet) does not exist
     */
    public function balanceOf(int $userId): Money;
}
