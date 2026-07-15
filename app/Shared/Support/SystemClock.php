<?php

declare(strict_types=1);

namespace App\Shared\Support;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

final class SystemClock implements ClockInterface
{
    /**
     * Anchored to UTC explicitly: the process's default timezone is not
     * guaranteed to be UTC (tests deliberately run in America/Sao_Paulo to
     * catch exactly this), and a naive DateTimeImmutable would carry
     * whatever that ambient zone is into anything built from now().
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
