<?php

declare(strict_types=1);

namespace App\Resilience;

use Hyperf\Redis\Redis;
use Psr\Clock\ClockInterface;

/**
 * Closed → open after N consecutive failures → half-open after the cooldown,
 * letting a single probe through (success closes, failure reopens).
 *
 * State is shared in Redis: Swoole workers are separate processes, so an
 * in-memory breaker would give each worker its own inconsistent view.
 */
final readonly class CircuitBreaker
{
    public function __construct(
        private Redis $redis,
        private ClockInterface $clock,
        private string $name,
        private int $failureThreshold,
        private int $cooldownSeconds,
    ) {
    }

    public function isOpen(): bool
    {
        $openedAt = $this->redis->get($this->key('opened_at'));

        if (!is_string($openedAt)) {
            return false;
        }

        if ($this->now() < (int) $openedAt + $this->cooldownSeconds) {
            return true;
        }

        return !$this->claimProbe();
    }

    public function recordSuccess(): void
    {
        $this->redis->del($this->key('failures'), $this->key('opened_at'), $this->key('probe'));
    }

    public function recordFailure(): void
    {
        if ($this->redis->exists($this->key('opened_at'))) {
            $this->reopen();

            return;
        }

        if ($this->redis->incr($this->key('failures')) >= $this->failureThreshold) {
            $this->reopen();
        }
    }

    public function retryAfterSeconds(): int
    {
        $openedAt = $this->redis->get($this->key('opened_at'));

        if (!is_string($openedAt)) {
            return 0;
        }

        return max(0, (int) $openedAt + $this->cooldownSeconds - $this->now());
    }

    private function reopen(): void
    {
        $this->redis->set($this->key('opened_at'), (string) $this->now());
        $this->redis->del($this->key('failures'), $this->key('probe'));
    }

    private function claimProbe(): bool
    {
        return (bool) $this->redis->set(
            $this->key('probe'),
            '1',
            ['nx', 'ex' => $this->cooldownSeconds]
        );
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    private function key(string $suffix): string
    {
        return "breaker:{$this->name}:{$suffix}";
    }
}
