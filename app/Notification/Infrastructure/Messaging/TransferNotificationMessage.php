<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Messaging;

use App\Transfer\Domain\Entity\Transfer;
use Hyperf\Amqp\Message\ProducerMessage;
use Hyperf\Amqp\Message\Type;

final class TransferNotificationMessage extends ProducerMessage
{
    protected string $exchange = 'transfers';

    protected string|Type $type = Type::DIRECT;

    /** @var array<int, string>|string */
    protected array|string $routingKey = 'transfer.completed';

    public function __construct(Transfer $transfer)
    {
        $this->payload = [
            'transfer_id' => $transfer->id,
            'payer' => $transfer->payerId,
            'payee' => $transfer->payeeId,
            'amount_cents' => $transfer->amount->cents,
        ];
        $this->properties['content_type'] = 'application/json';
    }
}
