<?php

declare(strict_types=1);

namespace App\Listener;

use App\Amqp\FailedTransferNotificationQueue;
use Hyperf\Amqp\Consumer;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class DeclareNotificationDeadLetterListener implements ListenerInterface
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
            $this->container->get(Consumer::class)->declare(new FailedTransferNotificationQueue());
        } catch (Throwable $exception) {
            // A broker outage at boot must not crash the worker; the DLQ
            // gets declared on the next restart, and drops just log until then.
            $this->logger->error('failed to declare the notification dead-letter queue', [
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
