<?php

declare(strict_types=1);

namespace App\Transfer\Domain\Entity;

use App\Wallet\Domain\ValueObject\Money;
use DateTimeImmutable;

final readonly class Transfer
{
    public function __construct(
        public int $id,
        public int $payerId,
        public int $payeeId,
        public Money $amount,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
