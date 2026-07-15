<?php

declare(strict_types=1);
use App\Notification\Domain\Gateway\NotifierInterface;
use App\Notification\Infrastructure\Gateway\NotifierFactory;
use App\Shared\Support\SystemClock;
use App\Transfer\Application\Service\TransferMoneyService;
use App\Transfer\Application\Service\TransferMoneyServiceFactory;
use App\Transfer\Domain\Gateway\TransferAuthorizerInterface;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Infrastructure\Gateway\TransferAuthorizerFactory;
use App\Transfer\Infrastructure\Persistence\TransferRepository;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Infrastructure\Persistence\UserRepository;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Infrastructure\Persistence\WalletRepository;
use Psr\Clock\ClockInterface;

return [
    UserRepositoryInterface::class => UserRepository::class,
    WalletRepositoryInterface::class => WalletRepository::class,
    TransferRepositoryInterface::class => TransferRepository::class,
    TransferAuthorizerInterface::class => TransferAuthorizerFactory::class,
    NotifierInterface::class => NotifierFactory::class,
    TransferMoneyService::class => TransferMoneyServiceFactory::class,
    ClockInterface::class => SystemClock::class,
];
