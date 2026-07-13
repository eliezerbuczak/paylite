<?php

declare(strict_types=1);

namespace App\Gateway;

use App\Domain\Gateway\TransferAuthorizerInterface;
use App\Resilience\CircuitBreaker;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\Redis;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

/**
 * Composes the authorizer port: HTTP adapter wrapped by the circuit
 * breaker decorator. The service layer only ever sees the interface.
 */
final class TransferAuthorizerFactory
{
    public function __invoke(ContainerInterface $container): TransferAuthorizerInterface
    {
        $config = $container->get(ConfigInterface::class);

        $client = $container->get(ClientFactory::class)->create([
            'connect_timeout' => (float) $config->get('authorizer.connect_timeout', 2.0),
            'timeout' => (float) $config->get('authorizer.timeout', 3.0),
        ]);

        $breaker = new CircuitBreaker(
            redis: $container->get(Redis::class),
            clock: $container->get(ClockInterface::class),
            name: 'authorizer',
            failureThreshold: (int) $config->get('authorizer.breaker.failure_threshold', 5),
            cooldownSeconds: (int) $config->get('authorizer.breaker.cooldown_seconds', 10),
        );

        return new CircuitBreakerAuthorizer(
            new HttpTransferAuthorizer($client, (string) $config->get('authorizer.url')),
            $breaker,
        );
    }
}
