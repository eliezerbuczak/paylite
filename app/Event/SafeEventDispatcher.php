<?php

declare(strict_types=1);

namespace App\Event;

use Hyperf\Logger\LoggerFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resilience decorator for post-commit dispatches: side effects hanging
 * off the event are best-effort, so a failing listener is logged and
 * never propagates into the response of an already-committed operation.
 *
 * Deliberately not the global binding: framework events must keep failing
 * loudly; this wraps only the use cases that dispatch after commit.
 */
final class SafeEventDispatcher implements EventDispatcherInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly EventDispatcherInterface $inner,
        LoggerFactory $loggerFactory,
    ) {
        $this->logger = $loggerFactory->get('event');
    }

    public function dispatch(object $event): object
    {
        try {
            return $this->inner->dispatch($event);
        } catch (Throwable $error) {
            $this->logger->error('event listener failed after commit', [
                'event' => $event::class,
                'reason' => $error->getMessage(),
            ]);

            return $event;
        }
    }
}
