<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Wallet\Infrastructure\Persistence;

use App\Wallet\Application\Provisioning\WalletProvisionerInterface;
use App\Wallet\Infrastructure\Persistence\WalletProvisioner;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use HyperfTest\Factory\UserFactory;
use HyperfTest\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(WalletProvisioner::class)]
class WalletProvisionerTest extends IntegrationTestCase
{
    public function test_creates_a_zero_balance_wallet_for_the_user(): void
    {
        $user = UserFactory::common();

        $this->provisioner()->provisionForUser($user->id);

        self::assertSame(
            0,
            (int) Db::table('wallets')->where('user_id', $user->id)->value('balance_cents')
        );
    }

    private function provisioner(): WalletProvisionerInterface
    {
        return ApplicationContext::getContainer()->get(WalletProvisionerInterface::class);
    }
}
