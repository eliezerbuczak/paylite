<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Domain\Gateway;

use App\Shared\Outbox\Domain\Entity\OutboxEvent;
use App\Shared\Outbox\Domain\Exception\OutboxPublishException;

/**
 * Delivers one outbox event to whatever channel its event_type maps to.
 * The adapter owns the translation from the generic outbox payload into
 * the wire format its destination expects — the outbox itself stays
 * agnostic of any one consumer's contract.
 */
interface OutboxEventPublisherInterface
{
    /**
     * @throws OutboxPublishException when the broker does not confirm publication
     */
    public function publish(OutboxEvent $event): void;
}
