<?php

declare(strict_types=1);

namespace App\Domain\Gateway;

use App\Domain\ValueObject\Money;

final readonly class TransferNotification
{
    public function __construct(
        public int $transferId,
        public int $payerId,
        public int $payeeId,
        public Money $amount,
    ) {
    }

    /**
     * Maps a queue payload back into the domain shape; null when the
     * message does not carry the expected fields.
     */
    public static function fromPayload(mixed $payload): ?self
    {
        if (!is_array($payload)) {
            return null;
        }

        $transferId = $payload['transfer_id'] ?? null;
        $payerId = $payload['payer'] ?? null;
        $payeeId = $payload['payee'] ?? null;
        $amountCents = $payload['amount_cents'] ?? null;

        if (!is_int($transferId) || !is_int($payerId) || !is_int($payeeId) || !is_int($amountCents)) {
            return null;
        }

        return new self($transferId, $payerId, $payeeId, Money::fromCents($amountCents));
    }
}
