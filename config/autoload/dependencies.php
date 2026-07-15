<?php

declare(strict_types=1);
use App\Domain\Gateway\NotifierInterface;
use App\Domain\Gateway\TransferAuthorizerInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\WalletRepositoryInterface;
use App\Gateway\NotifierFactory;
use App\Gateway\TransferAuthorizerFactory;
use App\Repository\UserRepository;
use App\Repository\WalletRepository;
use App\Service\TransferMoneyService;
use App\Service\TransferMoneyServiceFactory;
use App\Shared\Support\SystemClock;
use Psr\Clock\ClockInterface;

return [
    UserRepositoryInterface::class => UserRepository::class,
    WalletRepositoryInterface::class => WalletRepository::class,
    TransferAuthorizerInterface::class => TransferAuthorizerFactory::class,
    NotifierInterface::class => NotifierFactory::class,
    TransferMoneyService::class => TransferMoneyServiceFactory::class,
    ClockInterface::class => SystemClock::class,
];
