<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use Hyperf\DbConnection\Db;
use HyperfTest\Factory\WalletFactory;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @internal
 */
#[CoversNothing]
class WalletDepositTest extends FeatureTestCase
{
    public function test_deposits_into_the_wallet_and_returns_201_with_location(): void
    {
        $wallet = WalletFactory::withBalance(1000);

        $response = $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 50.0]);

        $response->assertStatus(201);

        $body = $response->json();
        self::assertIsInt($body['id']);
        self::assertSame(50.0, (float) $body['value']);
        self::assertArrayHasKey('created_at', $body);
        self::assertSame(
            "/wallets/{$wallet->user_id}/deposits/{$body['id']}",
            $response->getHeaderLine('Location')
        );

        self::assertSame(
            6000,
            (int) Db::table('wallets')->where('id', $wallet->id)->value('balance_cents')
        );
    }

    public function test_converts_decimal_value_to_cents_without_losing_a_cent(): void
    {
        $wallet = WalletFactory::withBalance(0);

        $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 19.99]);

        self::assertSame(
            1999,
            (int) Db::table('wallets')->where('id', $wallet->id)->value('balance_cents')
        );
    }

    public function test_rejects_deposit_for_unknown_user_with_404(): void
    {
        $response = $this->json('/wallets/999999/deposits', ['value' => 50.0]);

        $response->assertStatus(404);
        self::assertSame('USER_NOT_FOUND', $response->json()['error']['code']);
    }

    public function test_rejects_non_positive_value_with_422(): void
    {
        $wallet = WalletFactory::withBalance(0);

        $response = $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 0]);

        $response->assertStatus(422);
        self::assertSame('INVALID_AMOUNT', $response->json()['error']['code']);
    }

    public function test_rejects_value_with_more_than_two_decimal_places_with_422(): void
    {
        $wallet = WalletFactory::withBalance(0);

        $response = $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => 10.005]);

        $response->assertStatus(422);
        self::assertSame('INVALID_AMOUNT', $response->json()['error']['code']);
        self::assertSame(
            0,
            (int) Db::table('wallets')->where('id', $wallet->id)->value('balance_cents')
        );
    }

    public function test_rejects_missing_or_non_numeric_value_with_400(): void
    {
        $wallet = WalletFactory::withBalance(0);

        $response = $this->json("/wallets/{$wallet->user_id}/deposits", ['value' => '50.0']);

        $response->assertStatus(400);
        self::assertSame('MALFORMED_REQUEST', $response->json()['error']['code']);
    }
}
