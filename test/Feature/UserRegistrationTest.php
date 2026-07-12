<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use Hyperf\DbConnection\Db;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @internal
 */
#[CoversNothing]
class UserRegistrationTest extends FeatureTestCase
{
    private const VALID_PAYLOAD = [
        'full_name' => 'Jane Doe',
        'document' => '529.982.247-25',
        'email' => 'Jane@Example.com',
        'password' => 's3cret-pass',
        'type' => 'common',
    ];

    public function test_registers_a_user_and_returns_201_with_location(): void
    {
        $response = $this->json('/users', self::VALID_PAYLOAD);

        $response->assertStatus(201);

        $body = $response->json();
        self::assertIsInt($body['id']);
        self::assertSame('Jane Doe', $body['full_name']);
        self::assertSame('52998224725', $body['document']);
        self::assertSame('jane@example.com', $body['email']);
        self::assertSame('common', $body['type']);
        self::assertArrayHasKey('created_at', $body);
        self::assertSame("/users/{$body['id']}", $response->getHeaderLine('Location'));
    }

    public function test_never_exposes_password_fields(): void
    {
        $body = $this->json('/users', self::VALID_PAYLOAD)->json();

        self::assertArrayNotHasKey('password', $body);
        self::assertArrayNotHasKey('password_hash', $body);
    }

    public function test_creates_an_empty_wallet_for_the_new_user(): void
    {
        $body = $this->json('/users', self::VALID_PAYLOAD)->json();

        self::assertSame(
            0,
            (int) Db::table('wallets')->where('user_id', $body['id'])->value('balance_cents')
        );
    }

    public function test_rejects_duplicate_document_with_409(): void
    {
        $this->json('/users', self::VALID_PAYLOAD);

        $response = $this->json('/users', array_merge(self::VALID_PAYLOAD, [
            'email' => 'other@example.com',
        ]));

        $response->assertStatus(409);
        self::assertSame('DUPLICATE_DOCUMENT', $response->json()['error']['code']);
    }

    public function test_rejects_duplicate_email_with_409(): void
    {
        $this->json('/users', self::VALID_PAYLOAD);

        $response = $this->json('/users', array_merge(self::VALID_PAYLOAD, [
            'document' => '11.222.333/0001-81',
        ]));

        $response->assertStatus(409);
        self::assertSame('DUPLICATE_EMAIL', $response->json()['error']['code']);
    }

    public function test_rejects_invalid_document_with_422(): void
    {
        $response = $this->json('/users', array_merge(self::VALID_PAYLOAD, [
            'document' => '111.111.111-11',
        ]));

        $response->assertStatus(422);
        self::assertSame('INVALID_DOCUMENT', $response->json()['error']['code']);
    }

    public function test_rejects_unknown_user_type_with_422(): void
    {
        $response = $this->json('/users', array_merge(self::VALID_PAYLOAD, [
            'type' => 'admin',
        ]));

        $response->assertStatus(422);
        self::assertSame('INVALID_USER_TYPE', $response->json()['error']['code']);
    }

    public function test_rejects_missing_fields_with_400(): void
    {
        $response = $this->json('/users', ['full_name' => 'Jane Doe']);

        $response->assertStatus(400);
        self::assertSame('MALFORMED_REQUEST', $response->json()['error']['code']);
    }
}
