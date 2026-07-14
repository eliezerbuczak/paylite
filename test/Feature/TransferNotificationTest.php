<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use App\Amqp\TransferNotificationConsumer;
use App\Domain\Gateway\TransferAuthorizerInterface;
use Hyperf\Amqp\ConnectionFactory;
use Hyperf\Amqp\Consumer;
use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use HyperfTest\Factory\UserFactory;
use HyperfTest\Factory\WalletFactory;
use HyperfTest\Support\FakeTransferAuthorizer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * End to end against the real broker: a successful POST /transfer must
 * leave the notification message in the consumer queue.
 *
 * @internal
 */
#[CoversNothing]
class TransferNotificationTest extends FeatureTestCase
{
    private const QUEUE = 'transfer-notifications';

    protected function setUp(): void
    {
        parent::setUp();
        $container = ApplicationContext::getContainer();
        \assert($container instanceof Container);
        $container->set(TransferAuthorizerInterface::class, FakeTransferAuthorizer::authorizing());

        $container->get(Consumer::class)->declare($container->get(TransferNotificationConsumer::class));
        $this->channel()->queue_purge(self::QUEUE);
    }

    public function test_successful_transfer_leaves_a_notification_in_the_queue(): void
    {
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::forUser(UserFactory::merchant());

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);

        $response->assertStatus(201);
        $message = $this->waitForMessage();
        self::assertNotNull($message, 'expected a notification message in the queue');
        self::assertSame([
            'transfer_id' => $response->json()['id'],
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
            'amount_cents' => 10000,
        ], json_decode($message->getBody(), true));
    }

    public function test_rejected_transfer_publishes_nothing(): void
    {
        $payer = WalletFactory::withBalance(9999);
        $payee = WalletFactory::forUser(UserFactory::merchant());

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);

        $response->assertStatus(422);
        self::assertNull($this->waitForMessage(attempts: 5), 'no notification for a failed transfer');
    }

    private function waitForMessage(int $attempts = 20): ?AMQPMessage
    {
        $channel = $this->channel();

        for ($attempt = 0; $attempt < $attempts; ++$attempt) {
            $message = $channel->basic_get(self::QUEUE);

            if ($message !== null) {
                $channel->basic_ack($message->getDeliveryTag());

                return $message;
            }

            usleep(50_000);
        }

        return null;
    }

    private function channel(): AMQPChannel
    {
        return ApplicationContext::getContainer()
            ->get(ConnectionFactory::class)
            ->getConnection('default')
            ->getChannel();
    }
}
