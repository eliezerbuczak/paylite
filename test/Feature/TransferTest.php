<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use App\Shared\Infrastructure\Resilience\CircuitBreaker;
use App\Shared\Support\SystemClock;
use App\Transfer\Domain\Gateway\TransferAuthorizerInterface;
use App\Transfer\Infrastructure\Resilience\CircuitBreakerAuthorizer;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Container;
use Hyperf\Redis\Redis;
use HyperfTest\Factory\UserFactory;
use HyperfTest\Factory\WalletFactory;
use HyperfTest\Support\FakeTransferAuthorizer;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @internal
 */
#[CoversNothing]
class TransferTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bindAuthorizer(FakeTransferAuthorizer::authorizing());
    }

    public function test_transfers_money_and_returns_201_with_location(): void
    {
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::forUser(UserFactory::merchant());

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);

        $response->assertStatus(201);

        $body = $response->json();
        self::assertIsInt($body['id']);
        self::assertSame(100.0, (float) $body['value']);
        self::assertSame($payer->user_id, $body['payer']);
        self::assertSame($payee->user_id, $body['payee']);
        self::assertSame("/transfers/{$body['id']}", $response->getHeaderLine('Location'));

        self::assertSame(0, $this->balanceCents($payer->id));
        self::assertSame(10000, $this->balanceCents($payee->id));
        self::assertSame(
            10000,
            (int) Db::table('transfers')->where('id', $body['id'])->value('amount_cents')
        );
    }

    public function test_rejects_insufficient_balance_with_422(): void
    {
        $payer = WalletFactory::withBalance(9999);
        $payee = WalletFactory::forUser(UserFactory::merchant());

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);

        $response->assertStatus(422);
        self::assertSame('INSUFFICIENT_BALANCE', $response->json()['error']['code']);
        $this->assertNothingMoved($payer->id, 9999, $payee->id, 0);
    }

    public function test_rejects_merchant_payer_with_403(): void
    {
        $payer = WalletFactory::forUser(UserFactory::merchant(), ['balance_cents' => 10000]);
        $payee = WalletFactory::withBalance(0);

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);

        $response->assertStatus(403);
        self::assertSame('MERCHANT_CANNOT_TRANSFER', $response->json()['error']['code']);
        $this->assertNothingMoved($payer->id, 10000, $payee->id, 0);
    }

    public function test_rejects_transfer_to_self_with_422(): void
    {
        $payer = WalletFactory::withBalance(10000);

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payer->user_id,
        ]);

        $response->assertStatus(422);
        self::assertSame('SAME_PAYER_PAYEE', $response->json()['error']['code']);
    }

    public function test_rejects_unknown_payee_with_404(): void
    {
        $payer = WalletFactory::withBalance(10000);

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => 999999,
        ]);

        $response->assertStatus(404);
        self::assertSame('USER_NOT_FOUND', $response->json()['error']['code']);
        self::assertSame(10000, $this->balanceCents($payer->id));
    }

    public function test_rejects_transfer_when_authorizer_denies(): void
    {
        $this->bindAuthorizer(FakeTransferAuthorizer::denying());
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::forUser(UserFactory::merchant());

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);

        $response->assertStatus(422);
        self::assertSame('TRANSFER_NOT_AUTHORIZED', $response->json()['error']['code']);
        $this->assertNothingMoved($payer->id, 10000, $payee->id, 0);
    }

    public function test_answers_502_when_authorizer_is_unavailable(): void
    {
        $this->bindAuthorizer(FakeTransferAuthorizer::unavailable());
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::forUser(UserFactory::merchant());

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);

        $response->assertStatus(502);
        self::assertSame('AUTHORIZER_UNAVAILABLE', $response->json()['error']['code']);
        $this->assertNothingMoved($payer->id, 10000, $payee->id, 0);
    }

    public function test_answers_503_with_retry_after_when_the_breaker_is_open(): void
    {
        $this->bindAuthorizer(new CircuitBreakerAuthorizer(
            FakeTransferAuthorizer::unavailable(),
            new CircuitBreaker(
                redis: ApplicationContext::getContainer()->get(Redis::class),
                clock: new SystemClock(),
                name: uniqid('feature-authorizer-', true),
                failureThreshold: 1,
                cooldownSeconds: 30,
            ),
        ));
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::forUser(UserFactory::merchant());
        $payload = [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ];

        $first = $this->json('/transfer', $payload);
        $second = $this->json('/transfer', $payload);

        $first->assertStatus(502);
        $second->assertStatus(503);
        self::assertSame('SERVICE_UNAVAILABLE', $second->json()['error']['code']);
        self::assertGreaterThan(0, (int) $second->getHeaderLine('Retry-After'));
        $this->assertNothingMoved($payer->id, 10000, $payee->id, 0);
    }

    public function test_rejects_malformed_payload_with_400(): void
    {
        $payer = WalletFactory::withBalance(10000);

        $response = $this->json('/transfer', [
            'value' => '100.0',
            'payer' => $payer->user_id,
            'payee' => 15,
        ]);

        $response->assertStatus(400);
        self::assertSame('MALFORMED_REQUEST', $response->json()['error']['code']);
    }

    public function test_rejects_non_integer_payer_with_400(): void
    {
        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => '4',
            'payee' => 15,
        ]);

        $response->assertStatus(400);
        self::assertSame('MALFORMED_REQUEST', $response->json()['error']['code']);
    }

    public function test_replays_the_original_response_for_a_repeated_idempotency_key(): void
    {
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::forUser(UserFactory::merchant());
        $key = uniqid('idem-', true);
        $payload = [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ];

        $first = $this->json('/transfer', $payload, ['Idempotency-Key' => $key]);
        $second = $this->json('/transfer', $payload, ['Idempotency-Key' => $key]);

        $first->assertStatus(201);
        $second->assertStatus(201);
        self::assertSame($first->json()['id'], $second->json()['id']);
        self::assertSame('true', $second->getHeaderLine('Idempotent-Replayed'));

        self::assertSame(0, $this->balanceCents($payer->id));
        self::assertSame(10000, $this->balanceCents($payee->id));
        self::assertSame(1, (int) Db::table('transfers')->count());
    }

    private function bindAuthorizer(TransferAuthorizerInterface $authorizer): void
    {
        $container = ApplicationContext::getContainer();
        \assert($container instanceof Container);
        $container->set(TransferAuthorizerInterface::class, $authorizer);
    }

    private function balanceCents(int $walletId): int
    {
        return (int) Db::table('wallets')->where('id', $walletId)->value('balance_cents');
    }

    private function assertNothingMoved(int $payerWalletId, int $payerCents, int $payeeWalletId, int $payeeCents): void
    {
        self::assertSame($payerCents, $this->balanceCents($payerWalletId));
        self::assertSame($payeeCents, $this->balanceCents($payeeWalletId));
        self::assertSame(0, (int) Db::table('transfers')->count());
    }
}
