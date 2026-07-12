<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InvalidEmailException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The given e-mail address is not valid.');
    }

    public function errorCode(): string
    {
        return 'INVALID_EMAIL';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
