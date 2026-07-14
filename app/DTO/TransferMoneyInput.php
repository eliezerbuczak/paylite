<?php

declare(strict_types=1);

namespace App\DTO;

use App\Domain\ValueObject\Money;

final readonly class TransferMoneyInput
{
    public function __construct(
        public int $payerId,
        public int $payeeId,
        public Money $amount,
    ) {
    }
}
