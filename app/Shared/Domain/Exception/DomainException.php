<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use Exception;

abstract class DomainException extends Exception implements HttpErrorInterface
{
    /**
     * Machine-readable code used in the error response envelope.
     */
    abstract public function errorCode(): string;

    abstract public function httpStatus(): int;
}
