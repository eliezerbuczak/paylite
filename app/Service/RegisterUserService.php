<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\NewUser;
use App\Domain\Entity\User;
use App\Domain\Exception\InvalidFullNameException;
use App\Domain\Exception\InvalidPasswordException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Document;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserType;
use App\DTO\RegisterUserInput;
use SensitiveParameter;

final readonly class RegisterUserService
{
    private const MAX_FULL_NAME_LENGTH = 255;

    private const MAX_PASSWORD_LENGTH = 128;

    public function __construct(private UserRepositoryInterface $users)
    {
    }

    public function execute(#[SensitiveParameter] RegisterUserInput $input): User
    {
        $fullName = trim($input->fullName);
        if ($fullName === '') {
            throw InvalidFullNameException::empty();
        }

        if (mb_strlen($fullName) > self::MAX_FULL_NAME_LENGTH) {
            throw InvalidFullNameException::tooLong(self::MAX_FULL_NAME_LENGTH);
        }

        if (mb_strlen($input->password) > self::MAX_PASSWORD_LENGTH) {
            throw InvalidPasswordException::tooLong(self::MAX_PASSWORD_LENGTH);
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
