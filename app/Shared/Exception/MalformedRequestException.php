<?php

declare(strict_types=1);

namespace App\Shared\Exception;

use App\Shared\Domain\Exception\HttpErrorInterface;
use InvalidArgumentException;

final class MalformedRequestException extends InvalidArgumentException implements HttpErrorInterface
{
    public static function missingField(string $field): self
    {
        return new self("Field '{$field}' is required and must be a string.");
    }

    public static function missingIntegerField(string $field): self
    {
        return new self("Field '{$field}' is required and must be an integer.");
    }

    public static function missingNumericField(string $field): self
    {
        return new self("Field '{$field}' is required and must be a number.");
    }

    public function errorCode(): string
    {
        return 'MALFORMED_REQUEST';
    }

    public function httpStatus(): int
    {
        return 400;
    }
}
