<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Shared\Infrastructure\Resilience;

use App\Shared\Infrastructure\Resilience\CircuitBreaker;
use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\Redis;
use HyperfTest\Support\FakeClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Breaker state lives in Redis because Swoole workers are separate
 * processes — these tests exercise it against the real Redis.
 *
 * @internal
 */
#[CoversClass(CircuitBreaker::class)]
class CircuitBreakerTest extends TestCase
{
    private const THRESHOLD = 3;

    private const COOLDOWN = 10;

    private FakeClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FakeClock();
    }

    public function test_starts_closed(): void
    {
        self::assertFalse($this->breaker()->isOpen());
    }

    public function test_stays_closed_below_the_failure_threshold(): void
    {
        $breaker = $this->breaker();

        $breaker->recordFailure();
        $breaker->recordFailure();

        self::assertFalse($breaker->isOpen());
    }

    public function test_opens_after_consecutive_failures_reach_the_threshold(): void
    {
        $breaker = $this->breaker();

        $breaker->recordFailure();
        $breaker->recordFailure();
        $breaker->recordFailure();

        self::assertTrue($breaker->isOpen());
        self::assertSame(self::COOLDOWN, $breaker->retryAfterSeconds());
    }

    public function test_success_resets_the_consecutive_failure_count(): void
    {
        $breaker = $this->breaker();

        $breaker->recordFailure();
        $breaker->recordFailure();
        $breaker->recordSuccess();
        $breaker->recordFailure();
        $breaker->recordFailure();

        self::assertFalse($breaker->isOpen());
    }

    public function test_reports_remaining_cooldown_while_open(): void
    {
        $breaker = $this->openBreaker();

        $this->clock->advanceSeconds(4);

        self::assertTrue($breaker->isOpen());
        self::assertSame(self::COOLDOWN - 4, $breaker->retryAfterSeconds());
    }

    public function test_allows_a_single_probe_after_the_cooldown(): void
    {
        $breaker = $this->openBreaker();

        $this->clock->advanceSeconds(self::COOLDOWN);

        self::assertFalse($breaker->isOpen(), 'first caller after cooldown gets the probe');
        self::assertTrue($breaker->isOpen(), 'second caller is still rejected');
    }

    public function test_probe_success_closes_the_breaker(): void
    {
        $breaker = $this->openBreaker();
        $this->clock->advanceSeconds(self::COOLDOWN);
        $breaker->isOpen();

        $breaker->recordSuccess();

        self::assertFalse($breaker->isOpen());
        self::assertFalse($breaker->isOpen(), 'closed breaker admits every caller');
    }

    public function test_probe_failure_reopens_for_a_full_cooldown(): void
    {
        $breaker = $this->openBreaker();
        $this->clock->advanceSeconds(self::COOLDOWN);
        $breaker->isOpen();

        $breaker->recordFailure();

        self::assertTrue($breaker->isOpen());
        self::assertSame(self::COOLDOWN, $breaker->retryAfterSeconds());
    }

    private function openBreaker(): CircuitBreaker
    {
        $breaker = $this->breaker();

        for ($i = 0; $i < self::THRESHOLD; ++$i) {
            $breaker->recordFailure();
        }

        return $breaker;
    }

    private function breaker(): CircuitBreaker
    {
        return new CircuitBreaker(
            redis: ApplicationContext::getContainer()->get(Redis::class),
            clock: $this->clock,
            name: uniqid('breaker-test-', true),
            failureThreshold: self::THRESHOLD,
            cooldownSeconds: self::COOLDOWN,
        );
    }
}
