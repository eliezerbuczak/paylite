<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Gateway;

use App\Notification\Domain\Exception\NotifierUnavailableException;
use App\Notification\Domain\Gateway\NotifierInterface;
use App\Notification\Domain\Gateway\TransferNotification;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Throwable;

/**
 * The external contract answers 204 on success and 504 at random: the
 * service is unstable by design, so anything but a 2xx is unavailability
 * for the caller to retry.
 */
final readonly class HttpNotifier implements NotifierInterface
{
    public function __construct(
        private ClientInterface $client,
        private string $url,
    ) {
    }

    public function notify(TransferNotification $notification): void
    {
        try {
            $response = $this->client->request('POST', $this->url, [
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::JSON => [
                    'transfer_id' => $notification->transferId,
                    'payer' => $notification->payerId,
                    'payee' => $notification->payeeId,
                    'amount_cents' => $notification->amount->cents,
                ],
            ]);
        } catch (Throwable) {
            throw new NotifierUnavailableException();
        }

        if ($response->getStatusCode() >= 300) {
            throw new NotifierUnavailableException();
        }
    }
}
