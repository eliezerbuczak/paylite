<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Gateway;

use App\Domain\Exception\NotifierUnavailableException;
use App\Domain\Gateway\NotifierInterface;
use App\Domain\Gateway\TransferNotification;
use App\Gateway\CircuitBreakerNotifier;
use App\Shared\Exception\ServiceUnavailableException;
use App\Shared\Infrastructure\Resilience\CircuitBreaker;
use App\Wallet\Domain\ValueObject\Money;
use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\Redis;
use HyperfTest\Support\FakeClock;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Decorator over the notifier port with a real Redis-backed breaker:
 * when open, the consumer learns how long to wait before requeueing
 * instead of paying the timeout on a service known to be down.
 *
 * @internal
 */
#[CoversClass(CircuitBreakerNotifier::class)]
class CircuitBreakerNotifierTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const THRESHOLD = 2;

    private const COOLDOWN = 10;

    private FakeClock $clock;

    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FakeClock();
        $this->breaker = new CircuitBreaker(
            redis: ApplicationContext::getContainer()->get(Redis::class),
            clock: $this->clock,
            name: uniqid('notifier-test-', true),
            failureThreshold: self::THRESHOLD,
            cooldownSeconds: self::COOLDOWN,
        );
    }

    public function test_delegates_to_the_inner_notifier_while_closed(): void
    {
        $inner = Mockery::mock(NotifierInterface::class);
        $inner->shouldReceive('notify')->once()->with(Mockery::type(TransferNotification::class));

        $this->decorator($inner)->notify($this->notification());
    }

    public function test_opens_after_consecutive_unavailability_and_rejects_without_calling_inner(): void
    {
        $inner = Mockery::mock(NotifierInterface::class);
        $inner->shouldReceive('notify')
            ->times(self::THRESHOLD)
            ->andThrow(new NotifierUnavailableException());
        $decorator = $this->decorator($inner);

        for ($i = 0; $i < self::THRESHOLD; ++$i) {
            try {
                $decorator->notify($this->notification());
                self::fail('Expected NotifierUnavailableException.');
            } catch (NotifierUnavailableException) {
            }
        }

        try {
            $decorator->notify($this->notification());
            self::fail('Expected ServiceUnavailableException.');
        } catch (ServiceUnavailableException $exception) {
            self::assertSame(self::COOLDOWN, $exception->retryAfterSeconds());
        }
    }

    public function test_probe_success_after_cooldown_closes_the_breaker(): void
    {
        $inner = Mockery::mock(NotifierInterface::class);
        $inner->shouldReceive('notify')
            ->times(self::THRESHOLD)
            ->andThrow(new NotifierUnavailableException());
        $inner->shouldReceive('notify')->twice();
        $decorator = $this->decorator($inner);

        for ($i = 0; $i < self::THRESHOLD; ++$i) {
            try {
                $decorator->notify($this->notification());
            } catch (NotifierUnavailableException) {
            }
        }
        $this->clock->advanceSeconds(self::COOLDOWN);

        $decorator->notify($this->notification());
        $decorator->notify($this->notification());

        self::assertFalse($this->breaker->isOpen());
    }

    private function decorator(NotifierInterface $inner): CircuitBreakerNotifier
    {
        return new CircuitBreakerNotifier($inner, $this->breaker);
    }

    private function notification(): TransferNotification
    {
        return new TransferNotification(
            transferId: 1,
            payerId: 4,
            payeeId: 15,
            amount: Money::fromCents(10000),
        );
    }
}
