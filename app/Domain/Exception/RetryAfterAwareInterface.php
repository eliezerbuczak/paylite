<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Throwable;

/**
 * Errors that tell the caller how long to wait before retrying — as a
 * Retry-After header in HTTP responses, or as the requeue delay in queue
 * consumers.
 */
interface RetryAfterAwareInterface extends Throwable
{
    public function retryAfterSeconds(): int;
}
