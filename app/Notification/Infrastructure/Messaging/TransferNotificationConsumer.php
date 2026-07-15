<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Messaging;

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
    protected string $exchange = 'transfers';

    protected string|Type $type = Type::DIRECT;

    /** @var array<int, string>|string */
    protected array|string $routingKey = 'transfer.completed';

    protected ?string $queue = 'transfer-notifications';

    public function __construct(
        private readonly NotifyTransferHandler $handler,
        private readonly NotificationRetryScheduler $retryScheduler,
    ) {
    }

    public function consumeMessage(mixed $data, AMQPMessage $message): Result
    {
        $attempt = $this->attemptOf($message);

        return match ($this->handler->handle($data, $attempt)) {
            NotificationOutcome::Delivered, NotificationOutcome::Duplicate => Result::ACK,
            NotificationOutcome::RetryLater => $this->retryScheduler->schedule($data, $attempt + 1)
                ? Result::ACK
                : Result::REQUEUE,
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

    private function attemptOf(AMQPMessage $message): int
    {
        if (!$message->has('application_headers')) {
            return 0;
        }

        $headers = $message->get('application_headers');

        if (!$headers instanceof AMQPTable) {
            return 0;
        }

        $attempt = $headers->getNativeData()['x-attempts'] ?? 0;

        return is_int($attempt) ? $attempt : 0;
    }
}
