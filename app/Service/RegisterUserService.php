<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\NewUser;
use App\Domain\Entity\User;
use App\Domain\Exception\InvalidFullNameException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Document;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserType;
use App\DTO\RegisterUserInput;
use SensitiveParameter;

final readonly class RegisterUserService
{
    public function __construct(private UserRepositoryInterface $users)
    {
    }

    public function execute(#[SensitiveParameter] RegisterUserInput $input): User
    {
        $fullName = trim($input->fullName);
        if ($fullName === '') {
            throw new InvalidFullNameException();
        }

        $newUser = new NewUser(
            fullName: $fullName,
            document: Document::fromString($input->document),
            email: Email::fromString($input->email),
            passwordHash: password_hash($input->password, PASSWORD_ARGON2ID),
            type: UserType::fromString($input->type),
        );

        return $this->users->add($newUser);
    }
}
