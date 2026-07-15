<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence;

use App\Model\Wallet as WalletModel;
use App\User\Domain\Entity\NewUser;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\DuplicateDocumentException;
use App\User\Domain\Exception\DuplicateEmailException;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\Document;
use App\User\Domain\ValueObject\Email;
use App\User\Domain\ValueObject\UserType;
use App\User\Infrastructure\Model\User as UserModel;
use DateTimeImmutable;
use Hyperf\Database\Exception\QueryException;
use Hyperf\DbConnection\Db;

final class UserRepository implements UserRepositoryInterface
{
    public function add(NewUser $newUser): User
    {
        try {
            $model = Db::transaction(static function () use ($newUser): UserModel {
                $model = new UserModel();
                $model->fill([
                    'full_name' => $newUser->fullName,
                    'document' => $newUser->document->value,
                    'email' => $newUser->email->value,
                    'password_hash' => $newUser->passwordHash,
                    'type' => $newUser->type->value,
                ]);
                $model->save();

                $wallet = new WalletModel();
                $wallet->fill(['user_id' => $model->id]);
                $wallet->save();

                return $model;
            });
        } catch (QueryException $exception) {
            throw $this->translateUniqueViolation($exception);
        }

        return $this->toEntity($model);
    }

    public function findById(int $id): ?User
    {
        /** @var null|UserModel $model */
        $model = UserModel::query()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    private function translateUniqueViolation(QueryException $exception): DuplicateDocumentException|DuplicateEmailException|QueryException
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'users_document_unique')) {
            return new DuplicateDocumentException();
        }

        if (str_contains($message, 'users_email_unique')) {
            return new DuplicateEmailException();
        }

        return $exception;
    }

    private function toEntity(UserModel $model): User
    {
        return new User(
            id: $model->id,
            fullName: $model->full_name,
            document: Document::fromString($model->document),
            email: Email::fromString($model->email),
            type: UserType::from($model->type),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
