<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\Transfer;
use App\Domain\Exception\InsufficientBalanceException;
use App\Domain\Exception\InvalidAmountException;
use App\Domain\Exception\MerchantCannotTransferException;
use App\Domain\Exception\SamePayerPayeeException;
use App\Domain\Exception\TransferNotAuthorizedException;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Gateway\TransferAuthorizerInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\WalletRepositoryInterface;
use App\Domain\ValueObject\UserType;
use App\DTO\TransferMoneyInput;

/**
 * Fail cheap first: local validations, then a lock-free balance pre-check,
 * and only then the external authorizer. The authoritative balance check
 * happens again inside the repository, under lock.
 */
final readonly class TransferMoneyService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private WalletRepositoryInterface $wallets,
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

        if ($payer->type === UserType::Merchant) {
            throw new MerchantCannotTransferException();
        }

        if ($this->wallets->balanceOf($payer->id)->isLessThan($input->amount)) {
            throw new InsufficientBalanceException();
        }

        if (!$this->authorizer->isAuthorized()) {
            throw new TransferNotAuthorizedException();
        }

        return $this->wallets->transfer($input->payerId, $input->payeeId, $input->amount);
    }
}
