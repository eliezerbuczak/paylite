<?php

declare(strict_types=1);
use App\Notification\Domain\Gateway\NotifierInterface;
use App\Notification\Infrastructure\Gateway\NotifierFactory;
use App\Notification\Infrastructure\Messaging\TransferCompletedOutboxPublisher;
use App\Shared\Outbox\Application\PublishPendingOutboxEvents;
use App\Shared\Outbox\Application\PublishPendingOutboxEventsFactory;
use App\Shared\Outbox\Domain\Gateway\OutboxEventPublisherInterface;
use App\Shared\Outbox\Domain\Repository\OutboxEventRepositoryInterface;
use App\Shared\Outbox\Infrastructure\Persistence\OutboxEventRepository;
use App\Shared\Support\SystemClock;
use App\Transfer\Domain\Gateway\TransferAuthorizerInterface;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Infrastructure\Gateway\TransferAuthorizerFactory;
use App\Transfer\Infrastructure\Persistence\TransferRepository;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Infrastructure\Persistence\UserRepository;
use App\Wallet\Application\Provisioning\WalletProvisionerInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Infrastructure\Persistence\WalletProvisioner;
use App\Wallet\Infrastructure\Persistence\WalletRepository;
use Psr\Clock\ClockInterface;

return [
    UserRepositoryInterface::class => UserRepository::class,
    WalletRepositoryInterface::class => WalletRepository::class,
    WalletProvisionerInterface::class => WalletProvisioner::class,
    TransferRepositoryInterface::class => TransferRepository::class,
    OutboxEventRepositoryInterface::class => OutboxEventRepository::class,
    OutboxEventPublisherInterface::class => TransferCompletedOutboxPublisher::class,
    PublishPendingOutboxEvents::class => PublishPendingOutboxEventsFactory::class,
    TransferAuthorizerInterface::class => TransferAuthorizerFactory::class,
    NotifierInterface::class => NotifierFactory::class,
    ClockInterface::class => SystemClock::class,
];
