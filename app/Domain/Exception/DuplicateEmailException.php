<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class DuplicateEmailException extends DomainException
{
    public function __construct()
    {
        parent::__construct('A user with this e-mail already exists.');
    }

    public function errorCode(): string
    {
        return 'DUPLICATE_EMAIL';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
