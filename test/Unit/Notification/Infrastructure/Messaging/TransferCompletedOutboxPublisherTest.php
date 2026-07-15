<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Notification\Infrastructure\Messaging;

use App\Notification\Infrastructure\Messaging\TransferCompletedOutboxPublisher;
use App\Notification\Infrastructure\Messaging\TransferNotificationMessage;
use App\Shared\Outbox\Domain\Entity\OutboxEvent;
use App\Shared\Outbox\Domain\Exception\OutboxPublishException;
use Hyperf\Amqp\Producer;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(TransferCompletedOutboxPublisher::class)]
class TransferCompletedOutboxPublisherTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_publishes_the_transfer_notification_message(): void
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
        $publisher = new TransferCompletedOutboxPublisher($producer);

        $publisher->publish($this->event());

        $this->addToAssertionCount(1);
    }

    public function test_throws_when_the_broker_does_not_confirm_publication(): void
    {
        $producer = Mockery::mock(Producer::class);
        $producer->shouldReceive('produce')->once()->andReturnFalse();
        $publisher = new TransferCompletedOutboxPublisher($producer);

        $this->expectException(OutboxPublishException::class);

        $publisher->publish($this->event());
    }

    public function test_wraps_a_broker_failure_into_an_outbox_publish_exception(): void
    {
        $producer = Mockery::mock(Producer::class);
        $producer->shouldReceive('produce')->once()->andThrow(new RuntimeException('broker down'));
        $publisher = new TransferCompletedOutboxPublisher($producer);

        $this->expectException(OutboxPublishException::class);

        $publisher->publish($this->event());
    }

    public function test_rejects_an_event_type_it_does_not_know_how_to_translate(): void
    {
        $producer = Mockery::mock(Producer::class);
        $producer->shouldNotReceive('produce');
        $publisher = new TransferCompletedOutboxPublisher($producer);

        $this->expectException(OutboxPublishException::class);

        $publisher->publish($this->event(eventType: 'SomethingElseHappened'));
    }

    private function event(string $eventType = 'TransferCompleted'): OutboxEvent
    {
        return new OutboxEvent(
            id: 1,
            eventType: $eventType,
            aggregateType: 'transfer',
            aggregateId: 1,
            payload: [
                'transfer_id' => 1,
                'payer_id' => 4,
                'payee_id' => 15,
                'amount_cents' => 10000,
                'created_at' => '2026-07-12T12:00:00+00:00',
            ],
            attempts: 0,
        );
    }
}
