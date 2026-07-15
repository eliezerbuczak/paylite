<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Listener;

use App\Amqp\FailedTransferNotificationQueue;
use App\Amqp\TransferNotificationRetryQueue;
use App\Listener\DeclareNotificationQueuesListener;
use Hyperf\Amqp\Consumer;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Logger\LoggerFactory;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;

/**
 * @internal
 */
#[CoversClass(DeclareNotificationQueuesListener::class)]
class DeclareNotificationQueuesListenerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_listens_to_main_worker_start(): void
    {
        $listener = new DeclareNotificationQueuesListener($this->containerWith($this->untouchedAmqp()), $this->loggerFactory($this->untouchedLogger()));

        self::assertSame([MainWorkerStart::class], $listener->listen());
    }

    public function test_declares_the_retry_and_dead_letter_queues_on_worker_start(): void
    {
        $amqp = Mockery::mock(Consumer::class);
        $amqp->shouldReceive('declare')->once()->with(Mockery::type(TransferNotificationRetryQueue::class));
        $amqp->shouldReceive('declare')->once()->with(Mockery::type(FailedTransferNotificationQueue::class));
        $listener = new DeclareNotificationQueuesListener($this->containerWith($amqp), $this->loggerFactory($this->untouchedLogger()));

        $listener->process(new stdClass());
    }

    public function test_logs_instead_of_crashing_the_worker_when_the_broker_is_down(): void
    {
        $amqp = Mockery::mock(Consumer::class);
        $amqp->shouldReceive('declare')->once()->andThrow(new RuntimeException('broker down'));
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();
        $listener = new DeclareNotificationQueuesListener($this->containerWith($amqp), $this->loggerFactory($logger));

        $listener->process(new stdClass());
    }

    private function containerWith(Consumer $amqp): ContainerInterface
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(Consumer::class)->andReturn($amqp);

        return $container;
    }

    private function loggerFactory(LoggerInterface $logger): LoggerFactory
    {
        $factory = Mockery::mock(LoggerFactory::class);
        $factory->shouldReceive('get')->with('notification')->andReturn($logger);

        return $factory;
    }

    private function untouchedAmqp(): Consumer
    {
        $amqp = Mockery::mock(Consumer::class);
        $amqp->shouldNotReceive('declare');

        return $amqp;
    }

    private function untouchedLogger(): LoggerInterface
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('error');

        return $logger;
    }
}
