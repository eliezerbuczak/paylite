<?php

declare(strict_types=1);

namespace App\Amqp;

use App\Domain\Exception\NotifierUnavailableException;
use App\Domain\Exception\RetryAfterAwareInterface;
use App\Domain\Gateway\NotifierInterface;
use App\Domain\Gateway\TransferNotification;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

final class NotifyTransferHandler
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly NotifierInterface $notifier,
        private readonly NotificationRetryTracker $tracker,
        LoggerFactory $loggerFactory,
    ) {
        $this->logger = $loggerFactory->get('notification');
    }

    public function handle(mixed $payload): NotificationOutcome
    {
        $notification = TransferNotification::fromPayload($payload);

        if ($notification === null) {
            $this->logger->error('transfer notification payload is malformed', ['payload' => $payload]);

            return NotificationOutcome::GiveUp;
        }

        if (!$this->tracker->claim($notification->transferId)) {
            return NotificationOutcome::Duplicate;
        }

        try {
            $this->notifier->notify($notification);
        } catch (RetryAfterAwareInterface $circuitOpen) {
            $this->tracker->release($notification->transferId);
            $this->tracker->awaitSeconds($circuitOpen->retryAfterSeconds());

            return NotificationOutcome::RetryLater;
        } catch (NotifierUnavailableException) {
            $this->tracker->release($notification->transferId);

            if (!$this->tracker->scheduleRetry($notification->transferId)) {
                $this->logger->error('transfer notification exhausted its retries', [
                    'transfer_id' => $notification->transferId,
                ]);

                return NotificationOutcome::GiveUp;
            }

            return NotificationOutcome::RetryLater;
        }

        $this->tracker->forget($notification->transferId);

        return NotificationOutcome::Delivered;
    }
}
