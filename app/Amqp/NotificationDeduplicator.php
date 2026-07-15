<?php

declare(strict_types=1);

namespace App\Amqp;

use Hyperf\Redis\Redis;

/**
 * Redis-backed claim for at-least-once delivery: deduplicates redeliveries
 * whose ack was lost, so a transfer is never notified twice.
 */
final readonly class NotificationDeduplicator
{
    private const CLAIM_TTL_SECONDS = 86400;

    public function __construct(
        private Redis $redis,
    ) {
    }

    public function claim(int $transferId): bool
    {
        return (bool) $this->redis->set(
            $this->claimKey($transferId),
            '1',
            ['nx', 'ex' => self::CLAIM_TTL_SECONDS]
        );
    }

    public function release(int $transferId): void
    {
        $this->redis->del($this->claimKey($transferId));
    }

    private function claimKey(int $transferId): string
    {
        return "notified:{$transferId}";
    }
}
