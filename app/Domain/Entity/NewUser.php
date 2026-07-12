<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Document;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserType;

/**
 * A user ready to be persisted: validated values and hashed password.
 */
final readonly class NewUser
{
    public function __construct(
        public string $fullName,
        public Document $document,
        public Email $email,
        public string $passwordHash,
        public UserType $type,
    ) {
    }
}
