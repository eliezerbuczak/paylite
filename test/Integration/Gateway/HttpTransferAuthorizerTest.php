<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Gateway;

use App\Domain\Exception\AuthorizerUnavailableException;
use App\Gateway\HttpTransferAuthorizer;
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
 * external authorizer contract exactly.
 *
 * @internal
 */
#[CoversClass(HttpTransferAuthorizer::class)]
class HttpTransferAuthorizerTest extends TestCase
{
    public function test_interprets_success_response_as_authorized(): void
    {
        $authorizer = $this->authorizer(new Response(200, [], (string) json_encode([
            'status' => 'success',
            'data' => ['authorization' => true],
        ])));

        self::assertTrue($authorizer->isAuthorized());
    }

    public function test_interprets_denial_as_a_business_answer_even_with_http_403(): void
    {
        $authorizer = $this->authorizer(new Response(403, [], (string) json_encode([
            'status' => 'fail',
            'data' => ['authorization' => false],
        ])));

        self::assertFalse($authorizer->isAuthorized());
    }

    public function test_translates_5xx_into_unavailability(): void
    {
        $authorizer = $this->authorizer(new Response(500, [], 'Internal Server Error'));

        $this->expectException(AuthorizerUnavailableException::class);

        $authorizer->isAuthorized();
    }

    public function test_translates_network_failure_into_unavailability(): void
    {
        $authorizer = $this->authorizer(
            new ConnectException('timed out', new Request('GET', 'https://authorizer.test'))
        );

        $this->expectException(AuthorizerUnavailableException::class);

        $authorizer->isAuthorized();
    }

    public function test_translates_unexpected_body_into_unavailability(): void
    {
        $authorizer = $this->authorizer(new Response(200, [], (string) json_encode([
            'unexpected' => 'shape',
        ])));

        $this->expectException(AuthorizerUnavailableException::class);

        $authorizer->isAuthorized();
    }

    private function authorizer(ConnectException|Response $reply): HttpTransferAuthorizer
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([$reply]))]);

        return new HttpTransferAuthorizer($client, 'https://authorizer.test/api/v2/authorize');
    }
}
