<?php

declare(strict_types=1);

namespace App\Transfer\Application\Service;

use App\Transfer\Application\DTO\TransferMoneyInput;
use App\Transfer\Application\Event\TransferCompleted;
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
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Fail cheap first: local validations, then a lock-free balance pre-check,
 * and only then the external authorizer. The authoritative balance check
 * happens again inside the repository, under lock.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects") use case orchestrates
 * four ports plus the domain exceptions each rule speaks in
 */
final readonly class TransferMoneyService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private WalletRepositoryInterface $wallets,
        private TransferRepositoryInterface $transfers,
        private TransferAuthorizerInterface $authorizer,
        private EventDispatcherInterface $events,
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

        $transfer = $this->transfers->transfer($input->payerId, $input->payeeId, $input->amount);

        $this->events->dispatch(new TransferCompleted($transfer));

        return $transfer;
    }
}
