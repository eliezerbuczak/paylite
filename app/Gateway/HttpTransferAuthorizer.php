<?php

declare(strict_types=1);

namespace App\Gateway;

use App\Domain\Exception\AuthorizerUnavailableException;
use App\Domain\Gateway\TransferAuthorizerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Throwable;

/**
 * The external contract answers 200 {"data":{"authorization":true}} or
 * 403 {"data":{"authorization":false}}: a 403 here is a business denial,
 * not an HTTP error. Anything without a boolean answer is unavailability.
 */
final readonly class HttpTransferAuthorizer implements TransferAuthorizerInterface
{
    private const ANSWERABLE_STATUSES = [200, 403];

    public function __construct(
        private ClientInterface $client,
        private string $url,
    ) {
    }

    public function isAuthorized(): bool
    {
        try {
            $response = $this->client->request('GET', $this->url, [
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (Throwable) {
            throw new AuthorizerUnavailableException();
        }

        if (!in_array($response->getStatusCode(), self::ANSWERABLE_STATUSES, true)) {
            throw new AuthorizerUnavailableException();
        }

        $body = json_decode((string) $response->getBody(), true);
        $authorization = is_array($body) ? ($body['data']['authorization'] ?? null) : null;

        if (!is_bool($authorization)) {
            throw new AuthorizerUnavailableException();
        }

        return $authorization;
    }
}
