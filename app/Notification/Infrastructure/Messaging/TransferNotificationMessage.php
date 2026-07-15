<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Messaging;

use Hyperf\Amqp\Message\ProducerMessage;
use Hyperf\Amqp\Message\Type;

final class TransferNotificationMessage extends ProducerMessage
{
    protected string $exchange = 'transfers';

    protected string|Type $type = Type::DIRECT;

    /** @var array<int, string>|string */
    protected array|string $routingKey = 'transfer.completed';

    /**
     * @param array<string, mixed> $outboxPayload the TransferCompleted outbox
     *                                            event payload: transfer_id, payer_id, payee_id, amount_cents, created_at
     */
    public function __construct(array $outboxPayload)
    {
        $this->payload = [
            'transfer_id' => $outboxPayload['transfer_id'],
            'payer' => $outboxPayload['payer_id'],
            'payee' => $outboxPayload['payee_id'],
            'amount_cents' => $outboxPayload['amount_cents'],
        ];
        $this->properties['content_type'] = 'application/json';
    }
}
