<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidUserTypeException;

enum UserType: string
{
    case Common = 'common';
    case Merchant = 'merchant';

    public static function fromString(string $raw): self
    {
        return self::tryFrom($raw) ?? throw new InvalidUserTypeException();
    }
}
