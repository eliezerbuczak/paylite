<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InvalidFullNameException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Full name must not be empty.');
    }

    public function errorCode(): string
    {
        return 'INVALID_FULL_NAME';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
