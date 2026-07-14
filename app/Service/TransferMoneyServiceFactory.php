<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Gateway\TransferAuthorizerInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\WalletRepositoryInterface;
use App\Event\SafeEventDispatcher;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Composes the transfer use case with the safe dispatcher: the event fires
 * after the commit, so listener failures must never fail the response.
 */
final class TransferMoneyServiceFactory
{
    public function __invoke(ContainerInterface $container): TransferMoneyService
    {
        return new TransferMoneyService(
            $container->get(UserRepositoryInterface::class),
            $container->get(WalletRepositoryInterface::class),
            $container->get(TransferAuthorizerInterface::class),
            new SafeEventDispatcher(
                $container->get(EventDispatcherInterface::class),
                $container->get(LoggerFactory::class),
            ),
        );
    }
}
