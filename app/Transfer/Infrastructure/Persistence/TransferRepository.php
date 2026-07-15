<?php

declare(strict_types=1);

namespace App\Transfer\Infrastructure\Persistence;

use App\Shared\Outbox\Domain\Repository\OutboxEventRepositoryInterface;
use App\Transfer\Domain\Entity\Transfer;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Infrastructure\Model\Transfer as TransferModel;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;
use DateTimeImmutable;
use Hyperf\DbConnection\Db;

final class TransferRepository implements TransferRepositoryInterface
{
    private const EVENT_TYPE_TRANSFER_COMPLETED = 'TransferCompleted';

    private const AGGREGATE_TYPE_TRANSFER = 'transfer';

    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
        private readonly OutboxEventRepositoryInterface $outbox,
    ) {
    }

    public function transfer(int $payerId, int $payeeId, Money $amount): Transfer
    {
        $wallets = $this->wallets;
        $outbox = $this->outbox;
        $model = Db::transaction(static function () use ($payerId, $payeeId, $amount, $wallets, $outbox): TransferModel {
            $wallets->moveFunds($payerId, $payeeId, $amount);

            $transfer = new TransferModel();
            $transfer->fill([
                'payer_id' => $payerId,
                'payee_id' => $payeeId,
                'amount_cents' => $amount->cents,
            ]);
            $transfer->save();

            // Recorded in the same transaction as the money movement: a
            // crash after commit can no longer lose the notification, the
            // way a post-commit event dispatch could — the fact and the
            // event either both land or both roll back together.
            $outbox->record(
                eventType: self::EVENT_TYPE_TRANSFER_COMPLETED,
                aggregateType: self::AGGREGATE_TYPE_TRANSFER,
                aggregateId: $transfer->id,
                payload: [
                    'transfer_id' => $transfer->id,
                    'payer_id' => $transfer->payer_id,
                    'payee_id' => $transfer->payee_id,
                    'amount_cents' => $transfer->amount_cents,
                    'created_at' => $transfer->created_at->toIso8601String(),
                ],
            );

            return $transfer;
        });

        return new Transfer(
            id: $model->id,
            payerId: $model->payer_id,
            payeeId: $model->payee_id,
            amount: Money::fromCents($model->amount_cents),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
