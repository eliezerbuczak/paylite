<?php

declare(strict_types=1);

namespace App\Shared\Exception;

use App\Shared\Domain\Exception\HttpErrorInterface;
use RuntimeException;

final class IdempotentRequestInFlightException extends RuntimeException implements HttpErrorInterface
{
    public function __construct()
    {
        parent::__construct('A request with this Idempotency-Key is still being processed.');
    }

    public function errorCode(): string
    {
        return 'IDEMPOTENT_REQUEST_IN_FLIGHT';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
