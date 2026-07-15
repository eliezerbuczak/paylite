<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Resilience;

use App\Notification\Domain\Exception\NotifierUnavailableException;
use App\Notification\Domain\Gateway\NotifierInterface;
use App\Notification\Domain\Gateway\TransferNotification;
use App\Shared\Exception\ServiceUnavailableException;
use App\Shared\Infrastructure\Resilience\CircuitBreaker;

/**
 * Resilience decorator over the notifier port: when the breaker is open
 * the consumer is told how long to wait before requeueing instead of
 * paying the timeout on a service known to be down.
 */
final readonly class CircuitBreakerNotifier implements NotifierInterface
{
    public function __construct(
        private NotifierInterface $inner,
        private CircuitBreaker $breaker,
    ) {
    }

    /**
     * @throws NotifierUnavailableException propagated from the inner adapter, after counting the failure
     * @throws ServiceUnavailableException when the breaker is open and the call is rejected upfront
     */
    public function notify(TransferNotification $notification): void
    {
        if ($this->breaker->isOpen()) {
            throw new ServiceUnavailableException($this->breaker->retryAfterSeconds());
        }

        try {
            $this->inner->notify($notification);
        } catch (NotifierUnavailableException $error) {
            $this->breaker->recordFailure();

            throw $error;
        }

        $this->breaker->recordSuccess();
    }
}
