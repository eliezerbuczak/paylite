<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Messaging;

use Hyperf\Amqp\Consumer;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Declares the consumerless notification queues at boot: the retry parking
 * queue (TTL + dead-letter back into the main queue) and the failed queue
 * where exhausted notifications wait for inspection.
 */
final class DeclareNotificationQueuesListener implements ListenerInterface
{
    private readonly LoggerInterface $logger;

    /**
     * The AMQP Consumer is resolved lazily inside process(): its constructor
     * needs the event dispatcher, which instantiates every listener —
     * injecting it here would close a circular dependency.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        LoggerFactory $loggerFactory,
    ) {
        $this->logger = $loggerFactory->get('notification');
    }

    public function listen(): array
    {
        return [
            MainWorkerStart::class,
        ];
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter") required by ListenerInterface
     */
    public function process(object $event): void
    {
        try {
            $amqp = $this->container->get(Consumer::class);
            $amqp->declare(new TransferNotificationRetryQueue());
            $amqp->declare(new FailedTransferNotificationQueue());
        } catch (Throwable $exception) {
            // A broker outage at boot must not crash the worker; the queues
            // get declared on the next restart, and retries/drops just log
            // until then.
            $this->logger->error('failed to declare the notification queues', [
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
