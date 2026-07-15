<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidDocumentException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The given document is not a valid CPF or CNPJ.');
    }

    public function errorCode(): string
    {
        return 'INVALID_DOCUMENT';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
