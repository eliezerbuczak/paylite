<?php

declare(strict_types=1);

namespace App\Amqp;

use Hyperf\Amqp\Builder\QueueBuilder;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Message\Type;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Declaration-only parking queue for retries: nothing consumes it — no
 * #[Consumer] annotation on purpose. Failed notifications wait here until
 * the message TTL expires and the dead-letter routing sends them back to
 * the main queue, so the backoff costs no consumer time.
 */
final class TransferNotificationRetryQueue extends ConsumerMessage
{
    public const RETRY_DELAY_SECONDS = 30;

    protected string $exchange = 'transfers';

    protected string|Type $type = Type::DIRECT;

    /** @var array<int, string>|string */
    protected array|string $routingKey = 'transfer.completed.retry';

    protected ?string $queue = 'transfer-notifications.retry';

    public function getQueueBuilder(): QueueBuilder
    {
        return parent::getQueueBuilder()->setArguments(new AMQPTable([
            'x-message-ttl' => self::RETRY_DELAY_SECONDS * 1000,
            'x-dead-letter-exchange' => 'transfers',
            'x-dead-letter-routing-key' => 'transfer.completed',
        ]));
    }
}
