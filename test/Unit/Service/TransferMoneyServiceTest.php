<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service;

use App\Domain\Entity\Transfer;
use App\Domain\Exception\AuthorizerUnavailableException;
use App\Domain\Exception\SamePayerPayeeException;
use App\Domain\Exception\TransferNotAuthorizedException;
use App\Domain\Gateway\TransferAuthorizerInterface;
use App\DTO\TransferMoneyInput;
use App\Event\TransferCompleted;
use App\Service\TransferMoneyService;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\MerchantCannotTransferException;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\Document;
use App\User\Domain\ValueObject\Email;
use App\User\Domain\ValueObject\UserType;
use App\Wallet\Domain\Exception\InsufficientBalanceException;
use App\Wallet\Domain\Exception\InvalidAmountException;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(TransferMoneyService::class)]
class TransferMoneyServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_transfers_when_all_rules_pass(): void
    {
        $expected = new Transfer(
            id: 1,
            payerId: 4,
            payeeId: 15,
            amount: Money::fromCents(10000),
            createdAt: new DateTimeImmutable('2026-07-12 12:00:00'),
        );
        $users = $this->usersReturning(payer: $this->commonUser(4), payee: $this->merchantUser(15));
        $wallets = Mockery::mock(WalletRepositoryInterface::class);
        $wallets->shouldReceive('balanceOf')->with(4)->once()->andReturn(Money::fromCents(10000));
        $wallets->shouldReceive('transfer')
            ->once()
            ->withArgs(fn (int $payerId, int $payeeId, Money $amount): bool => $payerId === 4 && $payeeId === 15 && $amount->cents === 10000)
            ->andReturn($expected);
        $authorizer = $this->authorizerAnswering(true);

        $service = new TransferMoneyService($users, $wallets, $authorizer, $this->dispatcherAllowingAnything());
        $transfer = $service->execute(new TransferMoneyInput(payerId: 4, payeeId: 15, amount: Money::fromDecimal(100.0)));

        self::assertSame($expected, $transfer);
    }

    public function test_dispatches_transfer_completed_event_after_transfer(): void
    {
        $expected = new Transfer(
            id: 1,
            payerId: 4,
            payeeId: 15,
            amount: Money::fromCents(10000),
            createdAt: new DateTimeImmutable('2026-07-12 12:00:00'),
        );
        $users = $this->usersReturning(payer: $this->commonUser(4), payee: $this->merchantUser(15));
        $wallets = Mockery::mock(WalletRepositoryInterface::class);
        $wallets->shouldReceive('balanceOf')->with(4)->once()->andReturn(Money::fromCents(10000));
        $wallets->shouldReceive('transfer')->once()->andReturn($expected);
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn (object $event): bool => $event instanceof TransferCompleted && $event->transfer === $expected)
            ->andReturnUsing(fn (object $event): object => $event);

        $service = new TransferMoneyService($users, $wallets, $this->authorizerAnswering(true), $dispatcher);
        $service->execute(new TransferMoneyInput(payerId: 4, payeeId: 15, amount: Money::fromDecimal(100.0)));
    }

    public function test_rejects_transfer_to_self_before_touching_any_port(): void
    {
        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldNotReceive('findById');

        $service = new TransferMoneyService($users, $this->untouchedWallets(), $this->untouchedAuthorizer(), $this->untouchedDispatcher());

        $this->expectException(SamePayerPayeeException::class);

        $service->execute(new TransferMoneyInput(payerId: 4, payeeId: 4, amount: Money::fromDecimal(100.0)));
    }

    public function test_rejects_non_positive_amount(): void
    {
        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldNotReceive('findById');

        $service = new TransferMoneyService($users, $this->untouchedWallets(), $this->untouchedAuthorizer(), $this->untouchedDispatcher());

        $this->expectException(InvalidAmountException::class);

        $service->execute(new TransferMoneyInput(payerId: 4, payeeId: 15, amount: Money::fromCents(0)));
    }

    public function test_rejects_unknown_payer_with_user_not_found(): void
    {
        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldReceive('findById')->with(4)->andReturnNull();
        $users->shouldReceive('findById')->with(15)->andReturn($this->merchantUser(15));

        $service = new TransferMoneyService($users, $this->untouchedWallets(), $this->untouchedAuthorizer(), $this->untouchedDispatcher());

        $this->expectException(UserNotFoundException::class);

        $service->execute(new TransferMoneyInput(payerId: 4, payeeId: 15, amount: Money::fromDecimal(100.0)));
    }

    public function test_rejects_unknown_payee_with_user_not_found(): void
    {
        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldReceive('findById')->with(4)->andReturn($this->commonUser(4));
        $users->shouldReceive('findById')->with(15)->andReturnNull();

        $service = new TransferMoneyService($users, $this->untouchedWallets(), $this->untouchedAuthorizer(), $this->untouchedDispatcher());

        $this->expectException(UserNotFoundException::class);

        $service->execute(new TransferMoneyInput(payerId: 4, payeeId: 15, amount: Money::fromDecimal(100.0)));
    }

    public function test_rejects_merchant_payer_without_consulting_the_authorizer(): void
    {
        $users = $this->usersReturning(payer: $this->merchantUser(4), payee: $this->commonUser(15));

        $service = new TransferMoneyService($users, $this->untouchedWallets(), $this->untouchedAuthorizer(), $this->untouchedDispatcher());

        $this->expectException(MerchantCannotTransferException::class);

        $service->execute(new TransferMoneyInput(payerId: 4, payeeId: 15, amount: Money::fromDecimal(100.0)));
    }

    public function test_rejects_insufficient_balance_before_consulting_the_authorizer(): void
    {
        $users = $this->usersReturning(payer: $this->commonUser(4), payee: $this->merchantUser(15));
        $wallets = Mockery::mock(WalletRepositoryInterface::class);
        $wallets->shouldReceive('balanceOf')->with(4)->once()->andReturn(Money::fromCents(9999));
        $wallets->shouldNotReceive('transfer');

        $service = new TransferMoneyService($users, $wallets, $this->untouchedAuthorizer(), $this->untouchedDispatcher());

        $this->expectException(InsufficientBalanceException::class);

        $service->execute(new TransferMoneyInput(payerId: 4, payeeId: 15, amount: Money::fromDecimal(100.0)));
    }

    public function test_rejects_transfer_when_authorizer_denies(): void
    {
        $users = $this->usersReturning(payer: $this->commonUser(4), payee: $this->merchantUser(15));
        $wallets = Mockery::mock(WalletRepositoryInterface::class);
        $wallets->shouldReceive('balanceOf')->with(4)->once()->andReturn(Money::fromCents(10000));
        $wallets->shouldNotReceive('transfer');

        $service = new TransferMoneyService($users, $wallets, $this->authorizerAnswering(false), $this->untouchedDispatcher());

        $this->expectException(TransferNotAuthorizedException::class);

        $service->execute(new TransferMoneyInput(payerId: 4, payeeId: 15, amount: Money::fromDecimal(100.0)));
    }

    public function test_does_not_persist_when_authorizer_is_unavailable(): void
    {
        $users = $this->usersReturning(payer: $this->commonUser(4), payee: $this->merchantUser(15));
        $wallets = Mockery::mock(WalletRepositoryInterface::class);
        $wallets->shouldReceive('balanceOf')->with(4)->once()->andReturn(Money::fromCents(10000));
        $wallets->shouldNotReceive('transfer');
        $authorizer = Mockery::mock(TransferAuthorizerInterface::class);
        $authorizer->shouldReceive('isAuthorized')->once()->andThrow(new AuthorizerUnavailableException());

        $service = new TransferMoneyService($users, $wallets, $authorizer, $this->untouchedDispatcher());

        $this->expectException(AuthorizerUnavailableException::class);

        $service->execute(new TransferMoneyInput(payerId: 4, payeeId: 15, amount: Money::fromDecimal(100.0)));
    }

    private function usersReturning(User $payer, User $payee): MockInterface&UserRepositoryInterface
    {
        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldReceive('findById')->with($payer->id)->andReturn($payer);
        $users->shouldReceive('findById')->with($payee->id)->andReturn($payee);

        return $users;
    }

    private function untouchedWallets(): MockInterface&WalletRepositoryInterface
    {
        $wallets = Mockery::mock(WalletRepositoryInterface::class);
        $wallets->shouldNotReceive('balanceOf');
        $wallets->shouldNotReceive('transfer');

        return $wallets;
    }

    private function untouchedAuthorizer(): MockInterface&TransferAuthorizerInterface
    {
        $authorizer = Mockery::mock(TransferAuthorizerInterface::class);
        $authorizer->shouldNotReceive('isAuthorized');

        return $authorizer;
    }

    private function untouchedDispatcher(): EventDispatcherInterface&MockInterface
    {
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldNotReceive('dispatch');

        return $dispatcher;
    }

    private function dispatcherAllowingAnything(): EventDispatcherInterface&MockInterface
    {
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->andReturnUsing(fn (object $event): object => $event);

        return $dispatcher;
    }

    private function authorizerAnswering(bool $authorized): MockInterface&TransferAuthorizerInterface
    {
        $authorizer = Mockery::mock(TransferAuthorizerInterface::class);
        $authorizer->shouldReceive('isAuthorized')->once()->andReturn($authorized);

        return $authorizer;
    }

    private function commonUser(int $id): User
    {
        return $this->user($id, UserType::Common);
    }

    private function merchantUser(int $id): User
    {
        return $this->user($id, UserType::Merchant);
    }

    private function user(int $id, UserType $type): User
    {
        return new User(
            id: $id,
            fullName: 'Jane Doe',
            document: Document::fromString('52998224725'),
            email: Email::fromString("user{$id}@example.com"),
            type: $type,
            createdAt: new DateTimeImmutable('2026-07-12 10:00:00'),
        );
    }
}
