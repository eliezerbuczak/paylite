<?php

declare(strict_types=1);

namespace App\DTO;

use App\Domain\ValueObject\Money;

final readonly class DepositMoneyInput
{
    public function __construct(
        public int $userId,
        public Money $amount,
    ) {
    }
}
