<?php

declare(strict_types=1);

namespace App\Exception;

use App\Domain\Exception\HttpErrorInterface;
use App\Domain\Exception\RetryAfterAwareInterface;
use RuntimeException;

/**
 * Thrown when a circuit breaker is open: the dependency is known to be
 * down, so we reject immediately instead of paying the timeout.
 */
final class ServiceUnavailableException extends RuntimeException implements HttpErrorInterface, RetryAfterAwareInterface
{
    public function __construct(private readonly int $retryAfterSeconds)
    {
        parent::__construct('Service temporarily unavailable, try again later.');
    }

    public function errorCode(): string
    {
        return 'SERVICE_UNAVAILABLE';
    }

    public function httpStatus(): int
    {
        return 503;
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
