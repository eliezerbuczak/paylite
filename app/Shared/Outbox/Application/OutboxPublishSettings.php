<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Application;

final readonly class OutboxPublishSettings
{
    /**
     * @SuppressWarnings("PHPMD.LongVariable") the unit belongs in the name:
     * an unqualified $initialBackoff reads ambiguous (seconds? ms?) next
     * to sibling config values that all carry the same suffix
     */
    public function __construct(
        public int $batchSize,
        public int $maxAttempts,
        public int $initialBackoffSeconds,
        public int $maxBackoffSeconds,
    ) {
    }
}
