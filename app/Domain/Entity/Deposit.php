<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Money;
use DateTimeImmutable;

final readonly class Deposit
{
    public function __construct(
        public int $id,
        public int $walletId,
        public Money $amount,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
