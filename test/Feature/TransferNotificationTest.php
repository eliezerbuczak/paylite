<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use App\Amqp\TransferNotificationConsumer;
use App\Domain\Gateway\TransferAuthorizerInterface;
use App\Event\TransferCompleted;
use Hyperf\Amqp\ConnectionFactory;
use Hyperf\Amqp\Consumer;
use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use Hyperf\Event\ListenerData;
use Hyperf\Event\ListenerProvider;
use HyperfTest\Factory\UserFactory;
use HyperfTest\Factory\WalletFactory;
use HyperfTest\Support\FakeTransferAuthorizer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\CoversNothing;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionProperty;
use RuntimeException;

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

    /** @var null|array<int, ListenerData> */
    private ?array $originalListeners = null;

    protected function setUp(): void
    {
        parent::setUp();
        $container = ApplicationContext::getContainer();
        \assert($container instanceof Container);
        $container->set(TransferAuthorizerInterface::class, FakeTransferAuthorizer::authorizing());

        $container->get(Consumer::class)->declare($container->get(TransferNotificationConsumer::class));
        $this->channel()->queue_purge(self::QUEUE);
    }

    protected function tearDown(): void
    {
        if ($this->originalListeners !== null) {
            $provider = $this->listenerProvider();
            $provider->listeners = $this->originalListeners;
            $this->clearListenerCache($provider);
            $this->originalListeners = null;
        }
        parent::tearDown();
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

    public function test_transfer_still_succeeds_when_a_listener_explodes(): void
    {
        $this->registerExplodingListener();
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::forUser(UserFactory::merchant());

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);

        $response->assertStatus(201);
        self::assertNotNull(
            $this->waitForMessage(),
            'the publishing listener runs before the exploding one and still enqueues'
        );
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

    private function registerExplodingListener(): void
    {
        $provider = $this->listenerProvider();
        $this->originalListeners = $provider->listeners;
        // Negative priority: the real publishing listener must run first,
        // proving the explosion happens after the message is enqueued.
        $provider->on(TransferCompleted::class, static function (): void {
            throw new RuntimeException('listener exploded on purpose');
        }, -100);
        $this->clearListenerCache($provider);
    }

    private function listenerProvider(): ListenerProvider
    {
        $provider = ApplicationContext::getContainer()->get(ListenerProviderInterface::class);
        \assert($provider instanceof ListenerProvider);

        return $provider;
    }

    private function clearListenerCache(ListenerProvider $provider): void
    {
        (new ReflectionProperty(ListenerProvider::class, 'listenersCache'))->setValue($provider, []);
    }

    private function channel(): AMQPChannel
    {
        return ApplicationContext::getContainer()
            ->get(ConnectionFactory::class)
            ->getConnection('default')
            ->getChannel();
    }
}
