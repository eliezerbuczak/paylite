<?php

declare(strict_types=1);

namespace App\Transfer\Infrastructure\Persistence;

use App\Transfer\Domain\Entity\Transfer;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Infrastructure\Model\Transfer as TransferModel;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;
use DateTimeImmutable;
use Hyperf\DbConnection\Db;

final class TransferRepository implements TransferRepositoryInterface
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
    ) {
    }

    public function transfer(int $payerId, int $payeeId, Money $amount): Transfer
    {
        $wallets = $this->wallets;
        $model = Db::transaction(static function () use ($payerId, $payeeId, $amount, $wallets): TransferModel {
            $wallets->moveFunds($payerId, $payeeId, $amount);

            $transfer = new TransferModel();
            $transfer->fill([
                'payer_id' => $payerId,
                'payee_id' => $payeeId,
                'amount_cents' => $amount->cents,
            ]);
            $transfer->save();

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
