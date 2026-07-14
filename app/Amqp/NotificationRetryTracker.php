<?php

declare(strict_types=1);

namespace App\Amqp;

use App\Support\SleeperInterface;
use Hyperf\Redis\Redis;

/**
 * Redis-backed bookkeeping for at-least-once delivery: the claim key
 * deduplicates redeliveries whose ack was lost, and the attempts counter
 * bounds how long a failing notification keeps being retried.
 */
final readonly class NotificationRetryTracker
{
    private const CLAIM_TTL_SECONDS = 86400;

    private const ATTEMPTS_TTL_SECONDS = 3600;

    private const MAX_ATTEMPTS = 5;

    private const MAX_WAIT_SECONDS = 30;

    public function __construct(
        private Redis $redis,
        private SleeperInterface $sleeper,
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

    public function forget(int $transferId): void
    {
        $this->redis->del($this->attemptsKey($transferId));
    }

    /**
     * Counts one more failed attempt and waits the backoff before the
     * requeue; false when the message has exhausted its retries.
     */
    public function scheduleRetry(int $transferId): bool
    {
        $attempt = (int) $this->redis->incr($this->attemptsKey($transferId));
        $this->redis->expire($this->attemptsKey($transferId), self::ATTEMPTS_TTL_SECONDS);

        if ($attempt >= self::MAX_ATTEMPTS) {
            $this->forget($transferId);

            return false;
        }

        $this->sleeper->sleepSeconds(min(2 ** $attempt, self::MAX_WAIT_SECONDS));

        return true;
    }

    public function awaitSeconds(int $seconds): void
    {
        if ($seconds > 0) {
            $this->sleeper->sleepSeconds(min($seconds, self::MAX_WAIT_SECONDS));
        }
    }

    private function claimKey(int $transferId): string
    {
        return "notified:{$transferId}";
    }

    private function attemptsKey(int $transferId): string
    {
        return "notified:attempts:{$transferId}";
    }
}
