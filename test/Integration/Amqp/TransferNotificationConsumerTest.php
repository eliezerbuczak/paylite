<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Amqp;

use App\Amqp\NotificationRetryTracker;
use App\Amqp\NotifyTransferHandler;
use App\Amqp\TransferNotificationConsumer;
use App\Domain\Exception\NotifierUnavailableException;
use App\Domain\Gateway\NotifierInterface;
use App\Domain\Gateway\TransferNotification;
use App\Exception\ServiceUnavailableException;
use Hyperf\Amqp\Result;
use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use HyperfTest\Support\FakeSleeper;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Consumer against a real Redis for the dedup/retry bookkeeping; the
 * notifier port is mocked, as the tdd policy prescribes for gateways.
 *
 * @internal
 */
#[CoversClass(TransferNotificationConsumer::class)]
class TransferNotificationConsumerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private FakeSleeper $sleeper;

    private int $transferId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sleeper = new FakeSleeper();
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

    public function test_requeues_with_cooldown_and_retries_when_circuit_is_open(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andThrow(new ServiceUnavailableException(7))->ordered();
        $notifier->shouldReceive('notify')->once()->ordered();
        $consumer = $this->consumer($notifier);

        self::assertSame(Result::REQUEUE, $this->deliver($consumer, $this->payload()));
        self::assertSame([7], $this->sleeper->sleeps);
        self::assertSame(Result::ACK, $this->deliver($consumer, $this->payload()));
    }

    public function test_requeues_with_backoff_when_the_notifier_is_unavailable(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldReceive('notify')->once()->andThrow(new NotifierUnavailableException());

        $result = $this->deliver($this->consumer($notifier), $this->payload());

        self::assertSame(Result::REQUEUE, $result);
        self::assertSame([2], $this->sleeper->sleeps);
    }

    public function test_drops_to_the_dead_letter_queue_after_exhausting_retries(): void
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldReceive('notify')->times(5)->andThrow(new NotifierUnavailableException());
        $consumer = $this->consumer($notifier);

        for ($delivery = 1; $delivery <= 4; ++$delivery) {
            self::assertSame(Result::REQUEUE, $this->deliver($consumer, $this->payload()), "delivery {$delivery} should requeue");
        }

        self::assertSame(Result::DROP, $this->deliver($consumer, $this->payload()));
        self::assertSame([2, 4, 8, 16], $this->sleeper->sleeps, 'exponential backoff between retries');
    }

    public function test_queue_dead_letters_into_the_failed_routing_key(): void
    {
        $arguments = $this->consumer($this->untouchedNotifier())->getQueueBuilder()->getArguments();

        self::assertInstanceOf(AMQPTable::class, $arguments);
        $native = $arguments->getNativeData();
        self::assertSame('transfers', $native['x-dead-letter-exchange']);
        self::assertSame('transfer.completed.failed', $native['x-dead-letter-routing-key']);
    }

    private function consumer(NotifierInterface $notifier): TransferNotificationConsumer
    {
        $container = ApplicationContext::getContainer();
        $handler = new NotifyTransferHandler(
            $notifier,
            new NotificationRetryTracker($container->get(Redis::class), $this->sleeper),
            $container->get(LoggerFactory::class),
        );

        return new TransferNotificationConsumer($handler);
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

    private function deliver(TransferNotificationConsumer $consumer, mixed $payload): Result
    {
        return $consumer->consumeMessage($payload, new AMQPMessage());
    }

    private function untouchedNotifier(): MockInterface&NotifierInterface
    {
        $notifier = Mockery::mock(NotifierInterface::class);
        $notifier->shouldNotReceive('notify');

        return $notifier;
    }
}
