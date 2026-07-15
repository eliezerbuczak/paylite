<?php

declare(strict_types=1);

namespace App\Domain\Gateway;

use App\Domain\Exception\NotifierUnavailableException;
use App\Domain\Exception\RetryAfterAwareInterface;

interface NotifierInterface
{
    /**
     * Notifies the parties of a completed transfer. The external service is
     * known to be unstable; failure to acknowledge is an infrastructure
     * failure the caller is expected to retry.
     *
     * @throws NotifierUnavailableException when the notifier does not acknowledge
     * @throws RetryAfterAwareInterface when a resilience layer rejects the call upfront
     */
    public function notify(TransferNotification $notification): void;
}
