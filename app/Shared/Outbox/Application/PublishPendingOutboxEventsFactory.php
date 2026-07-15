<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Application;

use App\Shared\Outbox\Domain\Gateway\OutboxEventPublisherInterface;
use App\Shared\Outbox\Domain\Repository\OutboxEventRepositoryInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final class PublishPendingOutboxEventsFactory
{
    public function __invoke(ContainerInterface $container): PublishPendingOutboxEvents
    {
        $config = $container->get(ConfigInterface::class);

        return new PublishPendingOutboxEvents(
            $container->get(OutboxEventRepositoryInterface::class),
            $container->get(OutboxEventPublisherInterface::class),
            $container->get(ClockInterface::class),
            $container->get(LoggerFactory::class),
            new OutboxPublishSettings(
                batchSize: (int) $config->get('outbox.publish.batch_size', 50),
                maxAttempts: (int) $config->get('outbox.publish.max_attempts', 10),
                initialBackoffSeconds: (int) $config->get('outbox.publish.initial_backoff_seconds', 5),
                maxBackoffSeconds: (int) $config->get('outbox.publish.max_backoff_seconds', 300),
            ),
        );
    }
}
