<?php

declare(strict_types=1);

namespace App\Transfer\Domain\Repository;

use App\Transfer\Domain\Entity\Transfer;
use App\User\Domain\Exception\UserNotFoundException;
use App\Wallet\Domain\Exception\InsufficientBalanceException;
use App\Wallet\Domain\ValueObject\Money;

interface TransferRepositoryInterface
{
    /**
     * Debits the payer, credits the payee and records the transfer,
     * atomically, re-checking the payer's balance under lock.
     *
     * @throws UserNotFoundException when either wallet does not exist
     * @throws InsufficientBalanceException when the locked balance no longer covers the amount
     */
    public function transfer(int $payerId, int $payeeId, Money $amount): Transfer;
}
