<?php

declare(strict_types=1);

namespace App\Amqp;

use Hyperf\Amqp\Producer;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Parks failed notifications in the retry queue, so the backoff happens
 * in the broker instead of blocking the consumer.
 */
final class NotificationRetryScheduler
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly Producer $producer,
        LoggerFactory $loggerFactory,
    ) {
        $this->logger = $loggerFactory->get('notification');
    }

    /**
     * False when the parking publication itself fails: the caller still
     * holds the original message, and requeueing it is the only option
     * left that does not lose the notification.
     */
    public function schedule(mixed $payload, int $attempts): bool
    {
        try {
            $parked = $this->producer->produce(new TransferNotificationRetryMessage($payload, $attempts));
        } catch (Throwable $exception) {
            $this->logFailure($exception->getMessage());

            return false;
        }

        if (!$parked) {
            $this->logFailure('broker did not confirm publication');

            return false;
        }

        return true;
    }

    private function logFailure(string $reason): void
    {
        $this->logger->error('failed to park the notification for retry, requeueing', ['reason' => $reason]);
    }
}
