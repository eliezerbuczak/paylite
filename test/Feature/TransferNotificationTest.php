<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use App\Notification\Infrastructure\Messaging\TransferNotificationConsumer;
use App\Shared\Outbox\Application\PublishPendingOutboxEvents;
use App\Transfer\Domain\Gateway\TransferAuthorizerInterface;
use Hyperf\Amqp\ConnectionFactory;
use Hyperf\Amqp\Consumer;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Container;
use HyperfTest\Factory\UserFactory;
use HyperfTest\Factory\WalletFactory;
use HyperfTest\Support\FakeTransferAuthorizer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * End to end against the real broker: a successful POST /transfer records
 * a pending outbox event, and running the outbox publisher is what
 * actually leaves the notification message in the consumer queue — the
 * request itself never touches RabbitMQ.
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

    public function test_successful_transfer_records_a_pending_outbox_event(): void
    {
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::forUser(UserFactory::merchant());

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);

        $response->assertStatus(201);
        $transferId = $response->json()['id'];
        self::assertSame(
            'pending',
            Db::table('outbox_events')->where('aggregate_id', $transferId)->value('status')
        );
    }

    public function test_nothing_reaches_the_queue_before_the_outbox_publisher_runs(): void
    {
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::forUser(UserFactory::merchant());

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);

        $response->assertStatus(201);
        self::assertNull(
            $this->waitForMessage(attempts: 5),
            'the request must never publish to RabbitMQ directly — only the outbox publisher does'
        );
    }

    public function test_running_the_publisher_delivers_the_recorded_event_to_the_queue(): void
    {
        $payer = WalletFactory::withBalance(10000);
        $payee = WalletFactory::forUser(UserFactory::merchant());

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);
        $response->assertStatus(201);

        $this->runOutboxPublisher();

        $message = $this->waitForMessage();
        self::assertNotNull($message, 'expected a notification message in the queue after the publisher ran');
        self::assertSame([
            'transfer_id' => $response->json()['id'],
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
            'amount_cents' => 10000,
        ], json_decode($message->getBody(), true));
        self::assertSame(
            'published',
            Db::table('outbox_events')->where('aggregate_id', $response->json()['id'])->value('status')
        );
    }

    public function test_rejected_transfer_records_no_outbox_event(): void
    {
        $payer = WalletFactory::withBalance(9999);
        $payee = WalletFactory::forUser(UserFactory::merchant());
        $countBefore = (int) Db::table('outbox_events')->count();

        $response = $this->json('/transfer', [
            'value' => 100.0,
            'payer' => $payer->user_id,
            'payee' => $payee->user_id,
        ]);

        $response->assertStatus(422);
        self::assertSame($countBefore, (int) Db::table('outbox_events')->count());
        $this->runOutboxPublisher();
        self::assertNull($this->waitForMessage(attempts: 5), 'no notification for a failed transfer');
    }

    private function runOutboxPublisher(): void
    {
        ApplicationContext::getContainer()->get(PublishPendingOutboxEvents::class)->run();
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
