<?php

declare(strict_types=1);

namespace App\User\Application\DTO;

final readonly class RegisterUserInput
{
    public function __construct(
        public string $fullName,
        public string $document,
        public string $email,
        public string $password,
        public string $type,
    ) {
    }
}
