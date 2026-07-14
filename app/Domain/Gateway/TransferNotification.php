<?php

declare(strict_types=1);

namespace App\Domain\Gateway;

use App\Domain\ValueObject\Money;

final readonly class TransferNotification
{
    public function __construct(
        public int $transferId,
        public int $payerId,
        public int $payeeId,
        public Money $amount,
    ) {
    }
}
