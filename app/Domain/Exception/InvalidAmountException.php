<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidAmountException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function notPositive(): self
    {
        return new self('Amount must be greater than zero.');
    }

    public static function exceedsPrecision(): self
    {
        return new self('Amount must not have more than 2 decimal places.');
    }

    public static function tooLarge(): self
    {
        return new self('Amount exceeds the maximum allowed value.');
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
