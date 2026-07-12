<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\NewUser;
use App\Domain\Entity\User;
use App\Domain\Exception\DuplicateDocumentException;
use App\Domain\Exception\DuplicateEmailException;

interface UserRepositoryInterface
{
    /**
     * Persists the user together with their wallet, atomically.
     *
     * @throws DuplicateDocumentException
     * @throws DuplicateEmailException
     */
    public function add(NewUser $newUser): User;
}
