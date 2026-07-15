<?php

declare(strict_types=1);

namespace App\Transfer\Infrastructure\Persistence;

use App\Transfer\Domain\Entity\Transfer;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Infrastructure\Model\Transfer as TransferModel;
use App\User\Domain\Exception\UserNotFoundException;
use App\Wallet\Domain\Exception\InsufficientBalanceException;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Infrastructure\Model\Wallet as WalletModel;
use DateTimeImmutable;
use Hyperf\DbConnection\Db;

final class TransferRepository implements TransferRepositoryInterface
{
    public function transfer(int $payerId, int $payeeId, Money $amount): Transfer
    {
        $model = Db::transaction(static function () use ($payerId, $payeeId, $amount): TransferModel {
            [$payerWallet, $payeeWallet] = self::lockWalletPair($payerId, $payeeId);

            if ($payerWallet->balance_cents < $amount->cents) {
                throw new InsufficientBalanceException();
            }

            $payerWallet->balance_cents -= $amount->cents;
            $payerWallet->save();

            $payeeWallet->balance_cents += $amount->cents;
            $payeeWallet->save();

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

    /**
     * Locks both wallets in a deterministic order (wallet id) so two
     * concurrent transfers between the same pair cannot deadlock.
     *
     * @return array{WalletModel, WalletModel}
     */
    private static function lockWalletPair(int $payerId, int $payeeId): array
    {
        $wallets = WalletModel::query()
            ->whereIn('user_id', [$payerId, $payeeId])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /** @var null|WalletModel $payerWallet */
        $payerWallet = $wallets->firstWhere('user_id', $payerId);
        /** @var null|WalletModel $payeeWallet */
        $payeeWallet = $wallets->firstWhere('user_id', $payeeId);

        if ($payerWallet === null || $payeeWallet === null) {
            throw new UserNotFoundException();
        }

        return [$payerWallet, $payeeWallet];
    }
}
