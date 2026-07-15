<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Application;

final readonly class OutboxPublishSummary
{
    public function __construct(
        public int $published,
        public int $retried,
        public int $failed,
    ) {
    }
}
