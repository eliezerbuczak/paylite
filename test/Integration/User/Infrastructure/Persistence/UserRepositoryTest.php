<?php

declare(strict_types=1);

namespace HyperfTest\Integration\User\Infrastructure\Persistence;

use App\User\Domain\Entity\NewUser;
use App\User\Domain\Exception\DuplicateDocumentException;
use App\User\Domain\Exception\DuplicateEmailException;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\Document;
use App\User\Domain\ValueObject\Email;
use App\User\Domain\ValueObject\UserType;
use App\User\Infrastructure\Persistence\UserRepository;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use HyperfTest\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(UserRepository::class)]
class UserRepositoryTest extends IntegrationTestCase
{
    public function test_persists_user_together_with_an_empty_wallet(): void
    {
        $user = $this->repository()->add($this->newUser());

        self::assertGreaterThan(0, $user->id);
        self::assertSame('Jane Doe', $user->fullName);
        self::assertSame('52998224725', $user->document->value);
        self::assertSame('jane@example.com', $user->email->value);
        self::assertSame(UserType::Common, $user->type);

        self::assertTrue(Db::table('users')->where('id', $user->id)->exists());
        self::assertSame(
            0,
            (int) Db::table('wallets')->where('user_id', $user->id)->value('balance_cents')
        );
    }

    public function test_stores_the_password_hash_not_the_password(): void
    {
        $user = $this->repository()->add($this->newUser());

        $stored = (string) Db::table('users')->where('id', $user->id)->value('password_hash');

        self::assertTrue(password_verify('s3cret-pass', $stored));
    }

    public function test_translates_duplicate_document_into_domain_exception(): void
    {
        $repository = $this->repository();
        $repository->add($this->newUser());

        $this->expectException(DuplicateDocumentException::class);

        $repository->add($this->newUser(email: 'other@example.com'));
    }

    public function test_translates_duplicate_email_into_domain_exception(): void
    {
        $repository = $this->repository();
        $repository->add($this->newUser());

        $this->expectException(DuplicateEmailException::class);

        $repository->add($this->newUser(document: '11222333000181'));
    }

    public function test_finds_user_by_id(): void
    {
        $created = $this->repository()->add($this->newUser());

        $found = $this->repository()->findById($created->id);

        self::assertNotNull($found);
        self::assertSame($created->id, $found->id);
        self::assertSame('Jane Doe', $found->fullName);
        self::assertSame(UserType::Common, $found->type);
    }

    public function test_returns_null_for_unknown_user_id(): void
    {
        self::assertNull($this->repository()->findById(999999));
    }

    private function repository(): UserRepositoryInterface
    {
        return ApplicationContext::getContainer()->get(UserRepositoryInterface::class);
    }

    private function newUser(
        string $document = '52998224725',
        string $email = 'jane@example.com',
    ): NewUser {
        return new NewUser(
            fullName: 'Jane Doe',
            document: Document::fromString($document),
            email: Email::fromString($email),
            passwordHash: password_hash('s3cret-pass', PASSWORD_ARGON2ID),
            type: UserType::Common,
        );
    }
}
