<?php

declare(strict_types=1);

namespace App\Event;

use App\Domain\Entity\Transfer;

final readonly class TransferCompleted
{
    public function __construct(
        public Transfer $transfer,
    ) {
    }
}
