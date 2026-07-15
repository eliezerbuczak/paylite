<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Listener;

use App\Amqp\TransferNotificationMessage;
use App\Listener\TransferCompletedListener;
use App\Transfer\Application\Event\TransferCompleted;
use App\Transfer\Domain\Entity\Transfer;
use App\Wallet\Domain\ValueObject\Money;
use DateTimeImmutable;
use Hyperf\Amqp\Producer;
use Hyperf\Logger\LoggerFactory;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;

/**
 * @internal
 */
#[CoversClass(TransferCompletedListener::class)]
class TransferCompletedListenerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_listens_to_transfer_completed(): void
    {
        $listener = new TransferCompletedListener($this->untouchedProducer(), $this->loggerFactory($this->untouchedLogger()));

        self::assertSame([TransferCompleted::class], $listener->listen());
    }

    public function test_enqueues_notification_message_with_transfer_payload(): void
    {
        $producer = Mockery::mock(Producer::class);
        $producer->shouldReceive('produce')
            ->once()
            ->withArgs(fn (TransferNotificationMessage $message): bool => json_decode($message->payload(), true) === [
                'transfer_id' => 1,
                'payer' => 4,
                'payee' => 15,
                'amount_cents' => 10000,
            ])
            ->andReturnTrue();
        $listener = new TransferCompletedListener($producer, $this->loggerFactory($this->untouchedLogger()));

        $listener->process(new TransferCompleted($this->transfer()));
    }

    public function test_logs_instead_of_failing_when_publishing_throws(): void
    {
        $producer = Mockery::mock(Producer::class);
        $producer->shouldReceive('produce')->once()->andThrow(new RuntimeException('broker down'));
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->withArgs(
            fn (string $message, array $context): bool => $context['transfer_id'] === 1
        );
        $listener = new TransferCompletedListener($producer, $this->loggerFactory($logger));

        $listener->process(new TransferCompleted($this->transfer()));
    }

    public function test_logs_when_broker_does_not_confirm_publication(): void
    {
        $producer = Mockery::mock(Producer::class);
        $producer->shouldReceive('produce')->once()->andReturnFalse();
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->withArgs(
            fn (string $message, array $context): bool => $context['transfer_id'] === 1
        );
        $listener = new TransferCompletedListener($producer, $this->loggerFactory($logger));

        $listener->process(new TransferCompleted($this->transfer()));
    }

    public function test_ignores_unrelated_events(): void
    {
        $listener = new TransferCompletedListener($this->untouchedProducer(), $this->loggerFactory($this->untouchedLogger()));

        $listener->process(new stdClass());

        $this->addToAssertionCount(1);
    }

    private function transfer(): Transfer
    {
        return new Transfer(
            id: 1,
            payerId: 4,
            payeeId: 15,
            amount: Money::fromCents(10000),
            createdAt: new DateTimeImmutable('2026-07-12 12:00:00'),
        );
    }

    private function loggerFactory(LoggerInterface $logger): LoggerFactory
    {
        $factory = Mockery::mock(LoggerFactory::class);
        $factory->shouldReceive('get')->with('notification')->andReturn($logger);

        return $factory;
    }

    private function untouchedProducer(): MockInterface&Producer
    {
        $producer = Mockery::mock(Producer::class);
        $producer->shouldNotReceive('produce');

        return $producer;
    }

    private function untouchedLogger(): LoggerInterface&MockInterface
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('error');

        return $logger;
    }
}
