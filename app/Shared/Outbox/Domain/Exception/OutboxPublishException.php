<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Domain\Exception;

use RuntimeException;

/**
 * Thrown by an OutboxEventPublisherInterface adapter when the broker does
 * not confirm publication — network failure, nack, or an event type the
 * adapter does not know how to translate.
 */
final class OutboxPublishException extends RuntimeException
{
}
