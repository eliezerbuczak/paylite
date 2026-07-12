<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InvalidAmountException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Amount must be greater than zero.');
    }

    public function errorCode(): string
    {
        return 'INVALID_AMOUNT';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
