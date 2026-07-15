<?php

declare(strict_types=1);

namespace App\Wallet\Application\Service;

use App\Wallet\Application\DTO\DepositMoneyInput;
use App\Wallet\Domain\Entity\Deposit;
use App\Wallet\Domain\Exception\InvalidAmountException;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;

final readonly class DepositMoneyService
{
    public function __construct(private WalletRepositoryInterface $wallets)
    {
    }

    public function execute(DepositMoneyInput $input): Deposit
    {
        if (!$input->amount->isPositive()) {
            throw InvalidAmountException::notPositive();
        }

        return $this->wallets->deposit($input->userId, $input->amount);
    }
}
