<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Amqp;

use App\Amqp\NotificationDeduplicator;
use App\Amqp\NotificationRetryScheduler;
use App\Amqp\NotifyTransferHandler;
use App\Amqp\TransferNotificationConsumer;
use App\Amqp\TransferNotificationRetryMessage;
use App\Domain\Exception\NotifierUnavailableException;
use App\Domain\Gateway\NotifierInterface;
use App\Domain\Gateway\TransferNotification;
use App\Exception\ServiceUnavailableException;
use Hyperf\Amqp\Producer;
use Hyperf\Amqp\Result;
use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Consumer against a real Redis for the dedup bookkeeping; the notifier
 * port and the retry publication are mocked, as the tdd policy prescribes
 * for gateways and queues.
 *
 * @internal
 */
#[CoversClass(TransferNotificationConsumer::class)]
class TransferNotificationConsumerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private int $transferId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transferId = random_int(1_000_000, 2_000_000_000);
    }

    public function test_notifies_and_acks_on_first_delivery(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldReceive('notify')
            ->once()
            ->withArgs(fn (TransferNotification $notification): bool => $notification->transferId === $this->transferId
                && $notification->payerId === 4
                && $notification->payeeId === 15
                && $notification->amount->cents === 10000);

        $result = $this->deliver($this->consumer($notifier), $this->payload());

        self::assertSame(Result::ACK, $result);
    }

    public function test_acks_redelivery_of_an_already_notified_transfer_without_renotifying(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldReceive('notify')->once();
        $consumer = $this->consumer($notifier);

        self::assertSame(Result::ACK, $this->deliver($consumer, $this->payload()));
        self::assertSame(Result::ACK, $this->deliver($consumer, $this->payload()));
    }

    public function test_drops_malformed_payload_without_notifying(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldNotReceive('notify');

        $result = $this->deliver($this->consumer($notifier), ['unexpected' => 'shape']);

        self::assertSame(Result::DROP, $result);
    }

    public function test_parks_the_retry_and_acks_when_the_notifier_is_unavailable(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andThrow(new NotifierUnavailableException());
        $parked = $this->capturingProducer($captured);

        $result = $this->deliver($this->consumer($notifier, $parked), $this->payload());

        self::assertSame(Result::ACK, $result);
        self::assertInstanceOf(TransferNotificationRetryMessage::class, $captured);
        self::assertSame('transfer.completed.retry', $captured->getRoutingKey());
        self::assertSame(1, $this->attemptsOf($captured));
        self::assertSame($this->payload(), json_decode($captured->payload(), true));
    }

    public function test_parks_the_retry_and_acks_when_the_circuit_is_open(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andThrow(new ServiceUnavailableException(7));
        $parked = $this->capturingProducer($captured);

        $result = $this->deliver($this->consumer($notifier, $parked), $this->payload());

        self::assertSame(Result::ACK, $result);
        self::assertInstanceOf(TransferNotificationRetryMessage::class, $captured);
    }

    public function test_a_parked_retry_carries_the_accumulated_attempts(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andThrow(new NotifierUnavailableException());
        $parked = $this->capturingProducer($captured);

        $this->deliver($this->consumer($notifier, $parked), $this->payload(), attempts: 3);

        self::assertInstanceOf(TransferNotificationRetryMessage::class, $captured);
        self::assertSame(4, $this->attemptsOf($captured));
    }

    public function test_drops_to_the_dead_letter_queue_after_exhausting_retries(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andThrow(new NotifierUnavailableException());

        $result = $this->deliver(
            $this->consumer($notifier),
            $this->payload(),
            attempts: NotifyTransferHandler::MAX_ATTEMPTS - 1,
        );

        self::assertSame(Result::DROP, $result);
    }

    public function test_requeues_when_parking_the_retry_fails(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andThrow(new NotifierUnavailableException());
        $producer = Mockery::mock(Producer::class);
        $producer->shouldReceive('produce')->once()->andThrow(new RuntimeException('broker down'));

        $result = $this->deliver($this->consumer($notifier, $producer), $this->payload());

        self::assertSame(Result::REQUEUE, $result);
    }

    public function test_requeues_when_the_broker_does_not_confirm_the_parking(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andThrow(new NotifierUnavailableException());
        $producer = Mockery::mock(Producer::class);
        $producer->shouldReceive('produce')->once()->andReturnFalse();

        $result = $this->deliver($this->consumer($notifier, $producer), $this->payload());

        self::assertSame(Result::REQUEUE, $result);
    }

    public function test_queue_dead_letters_into_the_failed_routing_key(): void
    {
        $arguments = $this->consumer($this->untouchedNotifier())->getQueueBuilder()->getArguments();

        self::assertInstanceOf(AMQPTable::class, $arguments);
        $native = $arguments->getNativeData();
        self::assertSame('transfers', $native['x-dead-letter-exchange']);
        self::assertSame('transfer.completed.failed', $native['x-dead-letter-routing-key']);
    }

    private function consumer(NotifierInterface $notifier, ?Producer $producer = null): TransferNotificationConsumer
    {
        $container = ApplicationContext::getContainer();
        $handler = new NotifyTransferHandler(
            $notifier,
            new NotificationDeduplicator($container->get(Redis::class)),
            $container->get(LoggerFactory::class),
        );

        return new TransferNotificationConsumer(
            $handler,
            new NotificationRetryScheduler(
                $producer ?? $this->untouchedProducer(),
                $container->get(LoggerFactory::class),
            ),
        );
    }

    /**
     * @return array<string, int>
     */
    private function payload(): array
    {
        return [
            'transfer_id' => $this->transferId,
            'payer' => 4,
            'payee' => 15,
            'amount_cents' => 10000,
        ];
    }

    private function deliver(TransferNotificationConsumer $consumer, mixed $payload, int $attempts = 0): Result
    {
        $properties = $attempts > 0
            ? ['application_headers' => new AMQPTable(['x-attempts' => $attempts])]
            : [];

        return $consumer->consumeMessage($payload, new AMQPMessage('', $properties));
    }

    private function capturingProducer(?TransferNotificationRetryMessage &$captured): MockInterface&Producer
    {
        $producer = Mockery::mock(Producer::class);
        $producer->shouldReceive('produce')
            ->once()
            ->andReturnUsing(static function (TransferNotificationRetryMessage $message) use (&$captured): bool {
                $captured = $message;

                return true;
            });

        return $producer;
    }

    private function attemptsOf(TransferNotificationRetryMessage $message): int
    {
        $headers = $message->getProperties()['application_headers'] ?? null;
        self::assertInstanceOf(AMQPTable::class, $headers);
        $attempts = $headers->getNativeData()['x-attempts'] ?? null;
        self::assertIsInt($attempts);

        return $attempts;
    }

    private function untouchedProducer(): MockInterface&Producer
    {
        $producer = Mockery::mock(Producer::class);
        $producer->shouldNotReceive('produce');

        return $producer;
    }

    private function untouchedNotifier(): MockInterface&NotifierInterface
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldNotReceive('notify');

        return $notifier;
    }
}
