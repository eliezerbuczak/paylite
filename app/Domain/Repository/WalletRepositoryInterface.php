<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Deposit;
use App\Domain\Entity\Transfer;
use App\Domain\Exception\InsufficientBalanceException;
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

    /**
     * Current balance, read without any lock (cheap pre-check only —
     * the authoritative check happens inside transfer()).
     *
     * @throws UserNotFoundException when the user (and thus their wallet) does not exist
     */
    public function balanceOf(int $userId): Money;

    /**
     * Debits the payer, credits the payee and records the transfer,
     * atomically, re-checking the payer's balance under lock.
     *
     * @throws UserNotFoundException when either wallet does not exist
     * @throws InsufficientBalanceException when the locked balance no longer covers the amount
     */
    public function transfer(int $payerId, int $payeeId, Money $amount): Transfer;
}
