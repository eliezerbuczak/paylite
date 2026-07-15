<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class MerchantCannotTransferException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Merchants cannot send transfers.');
    }

    public function errorCode(): string
    {
        return 'MERCHANT_CANNOT_TRANSFER';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
