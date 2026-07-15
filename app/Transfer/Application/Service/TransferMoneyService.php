<?php

declare(strict_types=1);

namespace App\Transfer\Application\Service;

use App\Transfer\Application\DTO\TransferMoneyInput;
use App\Transfer\Domain\Entity\Transfer;
use App\Transfer\Domain\Exception\SamePayerPayeeException;
use App\Transfer\Domain\Exception\TransferNotAuthorizedException;
use App\Transfer\Domain\Gateway\TransferAuthorizerInterface;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\Wallet\Domain\Exception\InsufficientBalanceException;
use App\Wallet\Domain\Exception\InvalidAmountException;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;

/**
 * Fail cheap first: local validations, then a lock-free balance pre-check,
 * and only then the external authorizer. The authoritative balance check
 * happens again inside the repository, under lock.
 *
 * The TransferCompleted notification fact is recorded by the transfer
 * repository itself, in the same database transaction as the money
 * movement (transactional outbox) — there is no post-commit event to
 * dispatch here, so a crash right after this method returns can no
 * longer lose the notification.
 */
final readonly class TransferMoneyService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private WalletRepositoryInterface $wallets,
        private TransferRepositoryInterface $transfers,
        private TransferAuthorizerInterface $authorizer,
    ) {
    }

    public function execute(TransferMoneyInput $input): Transfer
    {
        if ($input->payerId === $input->payeeId) {
            throw new SamePayerPayeeException();
        }

        if (!$input->amount->isPositive()) {
            throw InvalidAmountException::notPositive();
        }

        $payer = $this->users->findById($input->payerId) ?? throw new UserNotFoundException();
        $this->users->findById($input->payeeId) ?? throw new UserNotFoundException();

        $payer->assertCanTransfer();

        if ($this->wallets->balanceOf($payer->id)->isLessThan($input->amount)) {
            throw new InsufficientBalanceException();
        }

        if (!$this->authorizer->isAuthorized()) {
            throw new TransferNotAuthorizedException();
        }

        return $this->transfers->transfer($input->payerId, $input->payeeId, $input->amount);
    }
}
