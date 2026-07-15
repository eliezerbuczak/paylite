<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

/**
 * Never mapped to an HTTP response: notification failures live in the
 * async consumer, where the reaction is retry, not a status code.
 */
final class NotifierUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Notification service is unavailable.');
    }
}
