<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class DuplicateDocumentException extends DomainException
{
    public function __construct()
    {
        parent::__construct('A user with this document already exists.');
    }

    public function errorCode(): string
    {
        return 'DUPLICATE_DOCUMENT';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
