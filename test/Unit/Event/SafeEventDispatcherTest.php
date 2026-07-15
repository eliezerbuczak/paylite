<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Event;

use App\Event\SafeEventDispatcher;
use Hyperf\Logger\LoggerFactory;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;

/**
 * @internal
 */
#[CoversClass(SafeEventDispatcher::class)]
class SafeEventDispatcherTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_delegates_to_the_inner_dispatcher_and_returns_its_result(): void
    {
        $event = new stdClass();
        $inner = Mockery::mock(EventDispatcherInterface::class);
        $inner->shouldReceive('dispatch')->once()->with($event)->andReturn($event);

        $dispatcher = new SafeEventDispatcher($inner, $this->loggerFactory($this->untouchedLogger()));

        self::assertSame($event, $dispatcher->dispatch($event));
    }

    public function test_logs_and_returns_the_event_when_a_listener_throws(): void
    {
        $event = new stdClass();
        $inner = Mockery::mock(EventDispatcherInterface::class);
        $inner->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('listener exploded'));
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->withArgs(
            fn (string $message, array $context): bool => $context['event'] === stdClass::class
        );

        $dispatcher = new SafeEventDispatcher($inner, $this->loggerFactory($logger));

        self::assertSame($event, $dispatcher->dispatch($event));
    }

    private function loggerFactory(LoggerInterface $logger): LoggerFactory
    {
        $factory = Mockery::mock(LoggerFactory::class);
        $factory->shouldReceive('get')->with('event')->andReturn($logger);

        return $factory;
    }

    private function untouchedLogger(): LoggerInterface
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('error');

        return $logger;
    }
}
