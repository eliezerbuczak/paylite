<?php

declare(strict_types=1);

namespace HyperfTest\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class FakeClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $now = '2026-07-12 12:00:00')
    {
        $this->now = new DateTimeImmutable($now);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advanceSeconds(int $seconds): void
    {
        $this->now = $this->now->modify("+{$seconds} seconds");
    }
}
