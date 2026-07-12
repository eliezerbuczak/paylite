<?php

declare(strict_types=1);
use App\Domain\Repository\UserRepositoryInterface;
use App\Repository\UserRepository;

return [
    UserRepositoryInterface::class => UserRepository::class,
];
