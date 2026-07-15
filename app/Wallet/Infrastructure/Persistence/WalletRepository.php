<?php

declare(strict_types=1);

namespace App\Wallet\Infrastructure\Persistence;

use App\User\Domain\Exception\UserNotFoundException;
use App\Wallet\Domain\Entity\Deposit;
use App\Wallet\Domain\Exception\InsufficientBalanceException;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryDirection;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Infrastructure\Model\Deposit as DepositModel;
use App\Wallet\Infrastructure\Model\LedgerEntry as LedgerEntryModel;
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

            self::recordLedgerEntry(
                walletId: $wallet->id,
                direction: LedgerEntryDirection::Credit,
                amountCents: $amount->cents,
                balanceAfterCents: $wallet->balance_cents,
                entryType: LedgerEntryType::Deposit,
                relatedDepositId: $deposit->id,
                relatedTransferId: null,
            );

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

    public function moveFunds(int $payerId, int $payeeId, Money $amount): void
    {
        [$payerWallet, $payeeWallet] = self::lockWalletPair($payerId, $payeeId);

        if ($payerWallet->balance_cents < $amount->cents) {
            throw new InsufficientBalanceException();
        }

        $payerWallet->balance_cents -= $amount->cents;
        $payerWallet->save();

        $payeeWallet->balance_cents += $amount->cents;
        $payeeWallet->save();
    }

    public function recordTransferLedger(int $payerId, int $payeeId, Money $amount, int $relatedTransferId): void
    {
        [$payerWallet, $payeeWallet] = self::lockWalletPair($payerId, $payeeId);

        self::recordLedgerEntry(
            walletId: $payerWallet->id,
            direction: LedgerEntryDirection::Debit,
            amountCents: $amount->cents,
            balanceAfterCents: $payerWallet->balance_cents,
            entryType: LedgerEntryType::Transfer,
            relatedDepositId: null,
            relatedTransferId: $relatedTransferId,
        );

        self::recordLedgerEntry(
            walletId: $payeeWallet->id,
            direction: LedgerEntryDirection::Credit,
            amountCents: $amount->cents,
            balanceAfterCents: $payeeWallet->balance_cents,
            entryType: LedgerEntryType::Transfer,
            relatedDepositId: null,
            relatedTransferId: $relatedTransferId,
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

    private static function recordLedgerEntry(
        int $walletId,
        LedgerEntryDirection $direction,
        int $amountCents,
        int $balanceAfterCents,
        LedgerEntryType $entryType,
        ?int $relatedDepositId,
        ?int $relatedTransferId,
    ): void {
        $entry = new LedgerEntryModel();
        $entry->fill([
            'wallet_id' => $walletId,
            'direction' => $direction->value,
            'amount_cents' => $amountCents,
            'balance_after_cents' => $balanceAfterCents,
            'entry_type' => $entryType->value,
            'related_deposit_id' => $relatedDepositId,
            'related_transfer_id' => $relatedTransferId,
        ]);
        $entry->save();
    }
}
