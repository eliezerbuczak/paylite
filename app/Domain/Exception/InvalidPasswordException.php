<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidPasswordException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function tooLong(int $maxLength): self
    {
        return new self("Password must not exceed {$maxLength} characters.");
    }

    public function errorCode(): string
    {
        return 'INVALID_PASSWORD';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
