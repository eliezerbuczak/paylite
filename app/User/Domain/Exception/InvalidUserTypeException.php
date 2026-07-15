<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidUserTypeException extends DomainException
{
    public function __construct()
    {
        parent::__construct('User type must be either "common" or "merchant".');
    }

    public function errorCode(): string
    {
        return 'INVALID_USER_TYPE';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
