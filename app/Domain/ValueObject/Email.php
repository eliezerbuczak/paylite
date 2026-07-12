<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidEmailException;

final readonly class Email
{
    private function __construct(public string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        $normalized = strtolower(trim($raw));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmailException();
        }

        return new self($normalized);
    }
}
