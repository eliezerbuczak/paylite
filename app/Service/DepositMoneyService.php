<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\Deposit;
use App\Domain\Exception\InvalidAmountException;
use App\Domain\Repository\WalletRepositoryInterface;
use App\DTO\DepositMoneyInput;

final readonly class DepositMoneyService
{
    public function __construct(private WalletRepositoryInterface $wallets)
    {
    }

    public function execute(DepositMoneyInput $input): Deposit
    {
        if (!$input->amount->isPositive()) {
            throw new InvalidAmountException();
        }

        return $this->wallets->deposit($input->userId, $input->amount);
    }
}
