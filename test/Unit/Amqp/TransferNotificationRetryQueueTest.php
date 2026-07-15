<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Amqp;

use App\Amqp\TransferNotificationRetryQueue;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TransferNotificationRetryQueue::class)]
class TransferNotificationRetryQueueTest extends TestCase
{
    public function test_parks_retries_on_a_dedicated_queue(): void
    {
        $queue = new TransferNotificationRetryQueue();

        self::assertSame('transfers', $queue->getExchange());
        self::assertSame('transfer.completed.retry', $queue->getRoutingKey());
        self::assertSame('transfer-notifications.retry', $queue->getQueue());
    }

    public function test_expired_messages_flow_back_into_the_main_queue(): void
    {
        $arguments = (new TransferNotificationRetryQueue())->getQueueBuilder()->getArguments();

        self::assertInstanceOf(AMQPTable::class, $arguments);
        $native = $arguments->getNativeData();
        self::assertSame(TransferNotificationRetryQueue::RETRY_DELAY_SECONDS * 1000, $native['x-message-ttl']);
        self::assertSame('transfers', $native['x-dead-letter-exchange']);
        self::assertSame('transfer.completed', $native['x-dead-letter-routing-key']);
    }
}
