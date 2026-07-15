<?php

declare(strict_types=1);

namespace App\Transfer\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class SamePayerPayeeException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Payer and payee must be different users.');
    }

    public function errorCode(): string
    {
        return 'SAME_PAYER_PAYEE';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
