<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Messaging;

use App\Shared\Outbox\Domain\Entity\OutboxEvent;
use App\Shared\Outbox\Domain\Exception\OutboxPublishException;
use App\Shared\Outbox\Domain\Gateway\OutboxEventPublisherInterface;
use Hyperf\Amqp\Producer;
use Throwable;

/**
 * The only outbox event type today is TransferCompleted, so this is the
 * only OutboxEventPublisherInterface binding: it owns the translation
 * from the generic outbox payload into the wire shape
 * TransferNotificationConsumer / NotifyTransferHandler already expect,
 * unchanged from before the outbox existed.
 */
final class TransferCompletedOutboxPublisher implements OutboxEventPublisherInterface
{
    private const SUPPORTED_EVENT_TYPE = 'TransferCompleted';

    public function __construct(
        private readonly Producer $producer,
    ) {
    }

    public function publish(OutboxEvent $event): void
    {
        if ($event->eventType !== self::SUPPORTED_EVENT_TYPE) {
            throw new OutboxPublishException("no publisher known for outbox event type '{$event->eventType}'");
        }

        try {
            $confirmed = $this->producer->produce(new TransferNotificationMessage($event->payload));
        } catch (Throwable $exception) {
            throw new OutboxPublishException($exception->getMessage(), previous: $exception);
        }

        if (!$confirmed) {
            throw new OutboxPublishException('broker did not confirm publication');
        }
    }
}
