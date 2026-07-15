<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Messaging;

use Hyperf\Amqp\Message\ProducerMessage;
use Hyperf\Amqp\Message\Type;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Republication of a failed notification into the retry parking queue,
 * carrying how many delivery attempts the payload has burned so far.
 */
final class TransferNotificationRetryMessage extends ProducerMessage
{
    protected string $exchange = 'transfers';

    protected string|Type $type = Type::DIRECT;

    /** @var array<int, string>|string */
    protected array|string $routingKey = 'transfer.completed.retry';

    public function __construct(mixed $payload, int $attempts)
    {
        $this->payload = $payload;
        $this->properties['content_type'] = 'application/json';
        $this->properties['application_headers'] = new AMQPTable(['x-attempts' => $attempts]);
    }
}
