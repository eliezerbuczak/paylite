<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Gateway;

use App\Notification\Domain\Gateway\NotifierInterface;
use App\Notification\Infrastructure\Resilience\CircuitBreakerNotifier;
use App\Shared\Infrastructure\Resilience\CircuitBreaker;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\Redis;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

/**
 * Composes the notifier port: HTTP adapter wrapped by the circuit
 * breaker decorator. The consumer only ever sees the interface.
 */
final class NotifierFactory
{
    public function __invoke(ContainerInterface $container): NotifierInterface
    {
        $config = $container->get(ConfigInterface::class);

        $client = $container->get(ClientFactory::class)->create([
            'connect_timeout' => (float) $config->get('notifier.connect_timeout', 2.0),
            'timeout' => (float) $config->get('notifier.timeout', 3.0),
        ]);

        $breaker = new CircuitBreaker(
            redis: $container->get(Redis::class),
            clock: $container->get(ClockInterface::class),
            name: 'notifier',
            failureThreshold: (int) $config->get('notifier.breaker.failure_threshold', 5),
            cooldownSeconds: (int) $config->get('notifier.breaker.cooldown_seconds', 10),
        );

        return new CircuitBreakerNotifier(
            new HttpNotifier($client, (string) $config->get('notifier.url')),
            $breaker,
        );
    }
}
