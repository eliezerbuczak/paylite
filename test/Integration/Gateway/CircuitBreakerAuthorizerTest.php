<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Gateway;

use App\Domain\Exception\AuthorizerUnavailableException;
use App\Domain\Gateway\TransferAuthorizerInterface;
use App\Exception\ServiceUnavailableException;
use App\Gateway\CircuitBreakerAuthorizer;
use App\Resilience\CircuitBreaker;
use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\Redis;
use HyperfTest\Support\FakeClock;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Decorator over the authorizer port with a real Redis-backed breaker:
 * the core never learns the breaker exists.
 *
 * @internal
 */
#[CoversClass(CircuitBreakerAuthorizer::class)]
class CircuitBreakerAuthorizerTest extends TestCase
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
            name: uniqid('authorizer-test-', true),
            failureThreshold: self::THRESHOLD,
            cooldownSeconds: self::COOLDOWN,
        );
    }

    public function test_delegates_to_the_inner_authorizer_while_closed(): void
    {
        $inner = Mockery::mock(TransferAuthorizerInterface::class);
        $inner->shouldReceive('isAuthorized')->once()->andReturnTrue();

        self::assertTrue($this->decorator($inner)->isAuthorized());
    }

    public function test_denial_is_a_business_answer_and_does_not_open_the_breaker(): void
    {
        $inner = Mockery::mock(TransferAuthorizerInterface::class);
        $inner->shouldReceive('isAuthorized')->times(self::THRESHOLD + 1)->andReturnFalse();
        $decorator = $this->decorator($inner);

        for ($i = 0; $i <= self::THRESHOLD; ++$i) {
            self::assertFalse($decorator->isAuthorized());
        }

        self::assertFalse($this->breaker->isOpen());
    }

    public function test_opens_after_consecutive_unavailability_and_rejects_without_calling_inner(): void
    {
        $inner = Mockery::mock(TransferAuthorizerInterface::class);
        $inner->shouldReceive('isAuthorized')
            ->times(self::THRESHOLD)
            ->andThrow(new AuthorizerUnavailableException());
        $decorator = $this->decorator($inner);

        for ($i = 0; $i < self::THRESHOLD; ++$i) {
            try {
                $decorator->isAuthorized();
                self::fail('Expected AuthorizerUnavailableException.');
            } catch (AuthorizerUnavailableException) {
            }
        }

        try {
            $decorator->isAuthorized();
            self::fail('Expected ServiceUnavailableException.');
        } catch (ServiceUnavailableException $exception) {
            self::assertSame(503, $exception->httpStatus());
            self::assertSame('SERVICE_UNAVAILABLE', $exception->errorCode());
            self::assertSame(self::COOLDOWN, $exception->retryAfterSeconds());
        }
    }

    public function test_probe_success_after_cooldown_closes_the_breaker(): void
    {
        $inner = Mockery::mock(TransferAuthorizerInterface::class);
        $inner->shouldReceive('isAuthorized')
            ->times(self::THRESHOLD)
            ->andThrow(new AuthorizerUnavailableException());
        $inner->shouldReceive('isAuthorized')->twice()->andReturnTrue();
        $decorator = $this->decorator($inner);

        for ($i = 0; $i < self::THRESHOLD; ++$i) {
            try {
                $decorator->isAuthorized();
            } catch (AuthorizerUnavailableException) {
            }
        }
        $this->clock->advanceSeconds(self::COOLDOWN);

        self::assertTrue($decorator->isAuthorized(), 'probe goes through to the inner authorizer');
        self::assertTrue($decorator->isAuthorized(), 'breaker closed again after the probe succeeded');
        self::assertFalse($this->breaker->isOpen());
    }

    private function decorator(TransferAuthorizerInterface $inner): CircuitBreakerAuthorizer
    {
        return new CircuitBreakerAuthorizer($inner, $this->breaker);
    }
}
