<?php

declare(strict_types=1);

namespace App\Transfer\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

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
