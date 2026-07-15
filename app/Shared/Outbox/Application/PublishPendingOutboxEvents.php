<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Application;

use App\Shared\Outbox\Domain\Entity\OutboxEvent;
use App\Shared\Outbox\Domain\Gateway\OutboxEventPublisherInterface;
use App\Shared\Outbox\Domain\Repository\OutboxEventRepositoryInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Claims and publishes one batch of pending outbox events. Each event is
 * claimed, published and settled independently — a failure on one never
 * stops the batch, and there is no wrapping transaction spanning the
 * broker call (see OutboxEventRepository::claimNext() for why).
 */
final class PublishPendingOutboxEvents
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly OutboxEventRepositoryInterface $outbox,
        private readonly OutboxEventPublisherInterface $publisher,
        private readonly ClockInterface $clock,
        LoggerFactory $loggerFactory,
        private readonly OutboxPublishSettings $settings,
    ) {
        $this->logger = $loggerFactory->get('outbox');
    }

    public function run(): OutboxPublishSummary
    {
        $published = 0;
        $retried = 0;
        $failed = 0;

        for ($processed = 0; $processed < $this->settings->batchSize; ++$processed) {
            $outcome = $this->processNext();

            if ($outcome === null) {
                break;
            }

            match ($outcome) {
                OutboxPublishOutcome::Published => ++$published,
                OutboxPublishOutcome::Retried => ++$retried,
                OutboxPublishOutcome::Failed => ++$failed,
            };
        }

        return new OutboxPublishSummary($published, $retried, $failed);
    }

    private function processNext(): ?OutboxPublishOutcome
    {
        $event = $this->outbox->claimNext();

        if ($event === null) {
            return null;
        }

        try {
            $this->publisher->publish($event);
        } catch (Throwable $exception) {
            return $this->handleFailure($event, $exception->getMessage());
        }

        $this->outbox->markPublished($event->id);

        return OutboxPublishOutcome::Published;
    }

    private function handleFailure(OutboxEvent $event, string $reason): OutboxPublishOutcome
    {
        $attemptsSoFar = $event->attempts + 1;

        if ($attemptsSoFar >= $this->settings->maxAttempts) {
            $this->outbox->markFailed($event->id, $reason);
            $this->logger->error('outbox event exhausted its retries', [
                'id' => $event->id,
                'event_type' => $event->eventType,
                'reason' => $reason,
            ]);

            return OutboxPublishOutcome::Failed;
        }

        $backoffSeconds = min(
            $this->settings->initialBackoffSeconds * 2 ** $event->attempts,
            $this->settings->maxBackoffSeconds,
        );
        $this->outbox->scheduleRetry(
            $event->id,
            $reason,
            $this->clock->now()->modify("+{$backoffSeconds} seconds"),
        );

        return OutboxPublishOutcome::Retried;
    }
}
