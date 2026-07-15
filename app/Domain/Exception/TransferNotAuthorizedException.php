<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class TransferNotAuthorizedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Transfer was not authorized.');
    }

    public function errorCode(): string
    {
        return 'TRANSFER_NOT_AUTHORIZED';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
