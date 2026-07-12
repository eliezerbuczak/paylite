<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InvalidFullNameException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function empty(): self
    {
        return new self('Full name must not be empty.');
    }

    public static function tooLong(int $maxLength): self
    {
        return new self("Full name must not exceed {$maxLength} characters.");
    }

    public function errorCode(): string
    {
        return 'INVALID_FULL_NAME';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
