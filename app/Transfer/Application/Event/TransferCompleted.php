<?php

declare(strict_types=1);

namespace App\Transfer\Application\Event;

use App\Transfer\Domain\Entity\Transfer;

final readonly class TransferCompleted
{
    public function __construct(
        public Transfer $transfer,
    ) {
    }
}
