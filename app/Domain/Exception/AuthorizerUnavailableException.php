<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class AuthorizerUnavailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Transfer authorization service is unavailable.');
    }

    public function errorCode(): string
    {
        return 'AUTHORIZER_UNAVAILABLE';
    }

    public function httpStatus(): int
    {
        return 502;
    }
}
