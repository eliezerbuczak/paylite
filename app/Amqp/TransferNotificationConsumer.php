<?php

declare(strict_types=1);

namespace App\Amqp;

use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Builder\QueueBuilder;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Message\Type;
use Hyperf\Amqp\Result;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

#[Consumer(exchange: 'transfers', routingKey: 'transfer.completed', queue: 'transfer-notifications', nums: 1)]
final class TransferNotificationConsumer extends ConsumerMessage
{
    protected string|Type $type = Type::DIRECT;

    public function __construct(
        private readonly NotifyTransferHandler $handler,
    ) {
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter") required by ConsumerMessageInterface
     */
    public function consumeMessage(mixed $data, AMQPMessage $message): Result
    {
        return match ($this->handler->handle($data)) {
            NotificationOutcome::Delivered, NotificationOutcome::Duplicate => Result::ACK,
            NotificationOutcome::RetryLater => Result::REQUEUE,
            NotificationOutcome::GiveUp => Result::DROP,
        };
    }

    public function getQueueBuilder(): QueueBuilder
    {
        // Dropped messages dead-letter back into the transfers exchange
        // under the failed routing key, landing in the inspection queue.
        return parent::getQueueBuilder()->setArguments(new AMQPTable([
            'x-ha-policy' => ['S', 'all'],
            'x-dead-letter-exchange' => 'transfers',
            'x-dead-letter-routing-key' => 'transfer.completed.failed',
        ]));
    }
}
