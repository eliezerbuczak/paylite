<?php

declare(strict_types=1);

namespace App\User\Domain\Entity;

use App\User\Domain\Exception\MerchantCannotTransferException;
use App\User\Domain\ValueObject\Document;
use App\User\Domain\ValueObject\Email;
use App\User\Domain\ValueObject\UserType;
use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $fullName,
        public Document $document,
        public Email $email,
        public UserType $type,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public function assertCanTransfer(): void
    {
        if ($this->type === UserType::Merchant) {
            throw new MerchantCannotTransferException();
        }
    }
}
