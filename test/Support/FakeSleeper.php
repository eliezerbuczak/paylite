<?php

declare(strict_types=1);

namespace HyperfTest\Support;

use App\Support\SleeperInterface;

final class FakeSleeper implements SleeperInterface
{
    /** @var array<int, int> */
    public array $sleeps = [];

    public function sleepSeconds(int $seconds): void
    {
        $this->sleeps[] = $seconds;
    }
}
