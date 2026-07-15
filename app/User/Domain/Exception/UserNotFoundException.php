<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class UserNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('User not found.');
    }

    public function errorCode(): string
    {
        return 'USER_NOT_FOUND';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
