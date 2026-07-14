<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Swoole runtime hooks turn sleep() into a coroutine yield, so waiting
 * here never blocks the worker process.
 */
final readonly class SystemSleeper implements SleeperInterface
{
    public function sleepSeconds(int $seconds): void
    {
        sleep($seconds);
    }
}
