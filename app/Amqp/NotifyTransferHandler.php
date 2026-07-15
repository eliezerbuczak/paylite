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
    public const MAX_ATTEMPTS = 10;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly NotifierInterface $notifier,
        private readonly NotificationDeduplicator $deduplicator,
        LoggerFactory $loggerFactory,
    ) {
        $this->logger = $loggerFactory->get('notification');
    }

    /**
     * @param int $attempt delivery attempts the message has already burned
     */
    public function handle(mixed $payload, int $attempt): NotificationOutcome
    {
        $notification = TransferNotification::fromPayload($payload);

        if ($notification === null) {
            $this->logger->error('transfer notification payload is malformed', ['payload' => $payload]);

            return NotificationOutcome::GiveUp;
        }

        if (!$this->deduplicator->claim($notification->transferId)) {
            return NotificationOutcome::Duplicate;
        }

        try {
            $this->notifier->notify($notification);
        } catch (NotifierUnavailableException|RetryAfterAwareInterface) {
            // A circuit-open rejection burns an attempt on purpose: during a
            // long outage messages age into the dead-letter queue, where they
            // can be replayed, instead of cycling through retries forever.
            $this->deduplicator->release($notification->transferId);

            if ($attempt + 1 >= self::MAX_ATTEMPTS) {
                $this->logger->error('transfer notification exhausted its retries', [
                    'transfer_id' => $notification->transferId,
                ]);

                return NotificationOutcome::GiveUp;
            }

            return NotificationOutcome::RetryLater;
        }

        return NotificationOutcome::Delivered;
    }
}
