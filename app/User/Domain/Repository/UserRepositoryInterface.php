<?php

declare(strict_types=1);

namespace App\User\Domain\Repository;

use App\User\Domain\Entity\NewUser;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\DuplicateDocumentException;
use App\User\Domain\Exception\DuplicateEmailException;

interface UserRepositoryInterface
{
    /**
     * Persists the user together with their wallet, atomically.
     *
     * @throws DuplicateDocumentException
     * @throws DuplicateEmailException
     */
    public function add(NewUser $newUser): User;

    public function findById(int $id): ?User;
}
