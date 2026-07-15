<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\User\Application\DTO\RegisterUserInput;
use App\User\Domain\Entity\NewUser;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\InvalidFullNameException;
use App\User\Domain\Exception\InvalidPasswordException;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\Document;
use App\User\Domain\ValueObject\Email;
use App\User\Domain\ValueObject\UserType;
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
