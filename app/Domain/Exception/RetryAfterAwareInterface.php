<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Errors that should carry a Retry-After header in the HTTP response.
 */
interface RetryAfterAwareInterface
{
    public function retryAfterSeconds(): int;
}
