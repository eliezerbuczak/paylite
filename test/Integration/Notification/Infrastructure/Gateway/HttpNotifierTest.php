<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Notification\Infrastructure\Gateway;

use App\Notification\Domain\Exception\NotifierUnavailableException;
use App\Notification\Domain\Gateway\TransferNotification;
use App\Notification\Infrastructure\Gateway\HttpNotifier;
use App\Wallet\Domain\ValueObject\Money;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the adapter against fake HTTP responses that reproduce the
 * external notifier contract exactly: 204 on success, 504 when unstable.
 *
 * @internal
 */
#[CoversClass(HttpNotifier::class)]
class HttpNotifierTest extends TestCase
{
    private MockHandler $server;

    public function test_posts_the_notification_and_accepts_204(): void
    {
        $notifier = $this->notifier(new Response(204));

        $notifier->notify($this->notification());

        $request = $this->server->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame([
            'transfer_id' => 1,
            'payer' => 4,
            'payee' => 15,
            'amount_cents' => 10000,
        ], json_decode((string) $request->getBody(), true));
    }

    public function test_translates_504_instability_into_unavailability(): void
    {
        $notifier = $this->notifier(new Response(504, [], 'Gateway Timeout'));

        $this->expectException(NotifierUnavailableException::class);

        $notifier->notify($this->notification());
    }

    public function test_translates_network_failure_into_unavailability(): void
    {
        $notifier = $this->notifier(
            new ConnectException('timed out', new Request('POST', 'https://notifier.test'))
        );

        $this->expectException(NotifierUnavailableException::class);

        $notifier->notify($this->notification());
    }

    private function notifier(ConnectException|Response $reply): HttpNotifier
    {
        $this->server = new MockHandler([$reply]);
        $client = new Client(['handler' => HandlerStack::create($this->server)]);

        return new HttpNotifier($client, 'https://notifier.test/api/v1/notify');
    }

    private function notification(): TransferNotification
    {
        return new TransferNotification(
            transferId: 1,
            payerId: 4,
            payeeId: 15,
            amount: Money::fromCents(10000),
        );
    }
}
