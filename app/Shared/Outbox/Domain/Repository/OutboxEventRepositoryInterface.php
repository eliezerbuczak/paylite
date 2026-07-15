<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Domain\Repository;

use App\Shared\Outbox\Domain\Entity\OutboxEvent;
use DateTimeImmutable;

interface OutboxEventRepositoryInterface
{
    /**
     * Records a new pending event for later delivery. Expected to run
     * inside the caller's transaction, so it commits or rolls back
     * together with whatever produced the fact being recorded.
     *
     * @param array<string, mixed> $payload
     */
    public function record(string $eventType, string $aggregateType, int $aggregateId, array $payload): void;

    /**
     * Claims and leases the oldest available pending event, skipping rows
     * a concurrent publisher already holds — null when there is none
     * ready right now.
     */
    public function claimNext(): ?OutboxEvent;

    public function markPublished(int $id): void;

    /**
     * Counts one more failed attempt, records why, and reschedules the
     * event for the given point in time.
     */
    public function scheduleRetry(int $id, string $reason, DateTimeImmutable $availableAt): void;

    /**
     * Terminal state: the event exhausted its retries.
     */
    public function markFailed(int $id, string $reason): void;
}
