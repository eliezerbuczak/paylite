<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use HyperfTest\Factory\WalletFactory;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @internal
 */
#[CoversNothing]
class IdempotentDepositTest extends FeatureTestCase
{
    public function test_replays_the_original_response_for_a_repeated_key(): void
    {
        $wallet = WalletFactory::withBalance(0);
        $key = uniqid('idem-', true);

        $first = $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 50.0], [
            'Idempotency-Key' => $key,
        ]);
        $second = $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 50.0], [
            'Idempotency-Key' => $key,
        ]);

        $first->assertStatus(201);
        $second->assertStatus(201);
        self::assertSame($first->json()['id'], $second->json()['id']);

        self::assertSame($key, $first->getHeaderLine('Idempotency-Key'));
        self::assertSame('', $first->getHeaderLine('Idempotent-Replayed'));
        self::assertSame($key, $second->getHeaderLine('Idempotency-Key'));
        self::assertSame('true', $second->getHeaderLine('Idempotent-Replayed'));

        self::assertSame(
            5000,
            (int) Db::table('wallets')->where('id', $wallet->id)->value('balance_cents')
        );
    }

    public function test_rejects_request_while_the_same_key_is_still_processing(): void
    {
        $wallet = WalletFactory::withBalance(0);
        $key = uniqid('idem-', true);
        $this->redis()->set(
            "idempotency:{$key}",
            json_encode(['state' => 'processing'], JSON_THROW_ON_ERROR),
            ['ex' => 60]
        );

        $response = $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 50.0], [
            'Idempotency-Key' => $key,
        ]);

        $response->assertStatus(409);
        self::assertSame('IDEMPOTENT_REQUEST_IN_FLIGHT', $response->json()['error']['code']);
    }

    public function test_replays_stored_business_failures_instead_of_reprocessing(): void
    {
        $wallet = WalletFactory::withBalance(0);
        $key = uniqid('idem-', true);

        $first = $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 0], [
            'Idempotency-Key' => $key,
        ]);
        $second = $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 50.0], [
            'Idempotency-Key' => $key,
        ]);

        $first->assertStatus(422);
        $second->assertStatus(422);
        self::assertSame('INVALID_AMOUNT', $second->json()['error']['code']);

        self::assertSame(
            0,
            (int) Db::table('wallets')->where('id', $wallet->id)->value('balance_cents')
        );
    }

    public function test_distinct_keys_are_processed_as_distinct_operations(): void
    {
        $wallet = WalletFactory::withBalance(0);

        $first = $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 50.0], [
            'Idempotency-Key' => uniqid('idem-', true),
        ]);
        $second = $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 50.0], [
            'Idempotency-Key' => uniqid('idem-', true),
        ]);

        $first->assertStatus(201);
        $second->assertStatus(201);
        self::assertNotSame($first->json()['id'], $second->json()['id']);

        self::assertSame(
            10000,
            (int) Db::table('wallets')->where('id', $wallet->id)->value('balance_cents')
        );
    }

    public function test_processes_normally_without_the_header(): void
    {
        $wallet = WalletFactory::withBalance(0);

        $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 50.0]);
        $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 50.0]);

        self::assertSame(
            10000,
            (int) Db::table('wallets')->where('id', $wallet->id)->value('balance_cents')
        );
    }

    private function redis(): Redis
    {
        return ApplicationContext::getContainer()->get(Redis::class);
    }
}
