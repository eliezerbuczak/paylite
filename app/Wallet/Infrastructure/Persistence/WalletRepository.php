<?php

declare(strict_types=1);

namespace App\Wallet\Infrastructure\Persistence;

use App\User\Domain\Exception\UserNotFoundException;
use App\Wallet\Domain\Entity\Deposit;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Infrastructure\Model\Deposit as DepositModel;
use App\Wallet\Infrastructure\Model\Wallet as WalletModel;
use DateTimeImmutable;
use Hyperf\DbConnection\Db;

final class WalletRepository implements WalletRepositoryInterface
{
    public function deposit(int $userId, Money $amount): Deposit
    {
        $model = Db::transaction(static function () use ($userId, $amount): DepositModel {
            /** @var null|WalletModel $wallet */
            $wallet = WalletModel::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                throw new UserNotFoundException();
            }

            $wallet->balance_cents += $amount->cents;
            $wallet->save();

            $deposit = new DepositModel();
            $deposit->fill([
                'wallet_id' => $wallet->id,
                'amount_cents' => $amount->cents,
            ]);
            $deposit->save();

            return $deposit;
        });

        return new Deposit(
            id: $model->id,
            walletId: $model->wallet_id,
            amount: Money::fromCents($model->amount_cents),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    public function balanceOf(int $userId): Money
    {
        $cents = WalletModel::query()->where('user_id', $userId)->value('balance_cents');

        if ($cents === null) {
            throw new UserNotFoundException();
        }

        return Money::fromCents((int) $cents);
    }
}
