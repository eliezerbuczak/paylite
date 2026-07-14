<?php

declare(strict_types=1);

namespace App\Support;

interface SleeperInterface
{
    public function sleepSeconds(int $seconds): void;
}
