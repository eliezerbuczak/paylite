<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service;

use App\Domain\Entity\NewUser;
use App\Domain\Entity\User;
use App\Domain\Exception\InvalidDocumentException;
use App\Domain\Exception\InvalidFullNameException;
use App\Domain\Exception\InvalidPasswordException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Document;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserType;
use App\DTO\RegisterUserInput;
use App\Service\RegisterUserService;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RegisterUserService::class)]
class RegisterUserServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_registers_user_normalizing_values_and_hashing_password(): void
    {
        $expected = $this->registeredUser();
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $repository->shouldReceive('add')
            ->once()
            ->withArgs(function (NewUser $newUser): bool {
                return $newUser->fullName === 'Jane Doe'
                    && $newUser->document->value === '52998224725'
                    && $newUser->email->value === 'jane@example.com'
                    && $newUser->type === UserType::Common
                    && password_verify('s3cret-pass', $newUser->passwordHash);
            })
            ->andReturn($expected);

        $service = new RegisterUserService($repository);
        $user = $service->execute(new RegisterUserInput(
            fullName: '  Jane Doe ',
            document: '529.982.247-25',
            email: 'Jane@Example.com',
            password: 's3cret-pass',
            type: 'common',
        ));

        self::assertSame($expected, $user);
    }

    public function test_rejects_invalid_document_without_touching_the_repository(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $repository->shouldNotReceive('add');

        $service = new RegisterUserService($repository);

        $this->expectException(InvalidDocumentException::class);

        $service->execute($this->input(document: '11111111111'));
    }

    public function test_rejects_empty_full_name(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $repository->shouldNotReceive('add');

        $service = new RegisterUserService($repository);

        $this->expectException(InvalidFullNameException::class);

        $service->execute($this->input(fullName: '   '));
    }

    public function test_rejects_full_name_longer_than_255_characters(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $repository->shouldNotReceive('add');

        $service = new RegisterUserService($repository);

        $this->expectException(InvalidFullNameException::class);

        $service->execute($this->input(fullName: str_repeat('a', 256)));
    }

    public function test_rejects_password_longer_than_128_characters(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $repository->shouldNotReceive('add');

        $service = new RegisterUserService($repository);

        $this->expectException(InvalidPasswordException::class);

        $service->execute($this->input(password: str_repeat('a', 129)));
    }

    private function input(
        string $fullName = 'Jane Doe',
        string $document = '52998224725',
        string $email = 'jane@example.com',
        string $password = 's3cret-pass',
        string $type = 'common',
    ): RegisterUserInput {
        return new RegisterUserInput(
            fullName: $fullName,
            document: $document,
            email: $email,
            password: $password,
            type: $type,
        );
    }

    private function registeredUser(): User
    {
        return new User(
            id: 1,
            fullName: 'Jane Doe',
            document: Document::fromString('52998224725'),
            email: Email::fromString('jane@example.com'),
            type: UserType::Common,
            createdAt: new DateTimeImmutable('2026-07-11 12:00:00'),
        );
    }
}
