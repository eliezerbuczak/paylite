<?php

declare(strict_types=1);

namespace App\Wallet\Application\DTO;

use App\Wallet\Domain\ValueObject\Money;

final readonly class DepositMoneyInput
{
    public function __construct(
        public int $userId,
        public Money $amount,
    ) {
    }
}
