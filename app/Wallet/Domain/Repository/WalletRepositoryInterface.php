<?php

declare(strict_types=1);

namespace App\Wallet\Domain\Repository;

use App\User\Domain\Exception\UserNotFoundException;
use App\Wallet\Domain\Entity\Deposit;
use App\Wallet\Domain\Exception\InsufficientBalanceException;
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
     * the authoritative re-check happens in moveFunds(), under lock).
     *
     * @throws UserNotFoundException when the user (and thus their wallet) does not exist
     */
    public function balanceOf(int $userId): Money;

    /**
     * Debits the payer and credits the payee, atomically, re-checking the
     * payer's balance under lock.
     *
     * @throws UserNotFoundException when either wallet does not exist
     * @throws InsufficientBalanceException when the locked balance no longer covers the amount
     */
    public function moveFunds(int $payerId, int $payeeId, Money $amount): void;

    /**
     * Records the debit/credit ledger trail for a transfer moveFunds()
     * already applied.
     */
    public function recordTransferLedger(int $payerId, int $payeeId, Money $amount, int $relatedTransferId): void;
}
