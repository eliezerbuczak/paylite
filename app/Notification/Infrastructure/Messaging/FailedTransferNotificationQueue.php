<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Messaging;

use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Message\Type;

/**
 * Declaration-only description of the dead-letter queue: exhausted
 * notifications land here for inspection. Nothing consumes it — no
 * #[Consumer] annotation on purpose.
 */
final class FailedTransferNotificationQueue extends ConsumerMessage
{
    protected string $exchange = 'transfers';

    protected string|Type $type = Type::DIRECT;

    /** @var array<int, string>|string */
    protected array|string $routingKey = 'transfer.completed.failed';

    protected ?string $queue = 'transfer-notifications.failed';
}
