<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Exceptions rendered as the standard error envelope:
 * {"error": {"code": ..., "message": ...}} with the given HTTP status.
 */
interface HttpErrorInterface
{
    public function errorCode(): string;

    public function httpStatus(): int;

    public function getMessage(): string;
}
