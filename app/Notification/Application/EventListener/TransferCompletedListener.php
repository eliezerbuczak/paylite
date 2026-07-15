<?php

declare(strict_types=1);

namespace App\Notification\Application\EventListener;

use App\Notification\Infrastructure\Messaging\TransferNotificationMessage;
use App\Transfer\Application\Event\TransferCompleted;
use Hyperf\Amqp\Producer;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Throwable;

final class TransferCompletedListener implements ListenerInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly Producer $producer,
        LoggerFactory $loggerFactory,
    ) {
        $this->logger = $loggerFactory->get('notification');
    }

    public function listen(): array
    {
        return [
            TransferCompleted::class,
        ];
    }

    public function process(object $event): void
    {
        if (!$event instanceof TransferCompleted) {
            return;
        }

        // Known MVP trade-off: a crash between commit and publish loses the
        // notification; a publish failure must never fail the transfer.
        try {
            $confirmed = $this->producer->produce(new TransferNotificationMessage($event->transfer));
        } catch (Throwable $exception) {
            $this->logFailure($event, $exception->getMessage());

            return;
        }

        if (!$confirmed) {
            $this->logFailure($event, 'broker did not confirm publication');
        }
    }

    private function logFailure(TransferCompleted $event, string $reason): void
    {
        $this->logger->error('transfer notification enqueue failed', [
            'transfer_id' => $event->transfer->id,
            'reason' => $reason,
        ]);
    }
}
