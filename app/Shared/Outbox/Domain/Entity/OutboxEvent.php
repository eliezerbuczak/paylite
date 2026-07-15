<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Domain\Entity;

final readonly class OutboxEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $id,
        public string $eventType,
        public string $aggregateType,
        public int $aggregateId,
        public array $payload,
        public int $attempts,
    ) {
    }
}
