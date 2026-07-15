<?php

declare(strict_types=1);

namespace App\Wallet\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InsufficientBalanceException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Insufficient balance to complete the transfer.');
    }

    public function errorCode(): string
    {
        return 'INSUFFICIENT_BALANCE';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
