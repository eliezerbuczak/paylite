<?php

declare(strict_types=1);

namespace HyperfTest\Factory;

use App\User\Infrastructure\Model\User;
use Faker\Factory;
use Faker\Generator;
use Faker\Provider\pt_BR\Company;
use Faker\Provider\pt_BR\Person;

final class UserFactory
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function common(array $overrides = []): User
    {
        return self::persist('common', $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function merchant(array $overrides = []): User
    {
        return self::persist('merchant', $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function persist(string $type, array $overrides): User
    {
        $faker = self::faker();

        $user = new User();
        $user->fill(array_merge([
            'full_name' => $faker->name(),
            'document' => $type === 'merchant'
                ? (new Company($faker))->cnpj(false)
                : (new Person($faker))->cpf(false),
            'email' => $faker->unique()->safeEmail(),
            'password_hash' => password_hash('secret', PASSWORD_ARGON2ID),
            'type' => $type,
        ], $overrides));
        $user->save();

        return $user;
    }

    private static function faker(): Generator
    {
        static $faker = null;

        return $faker ??= Factory::create('pt_BR');
    }
}
