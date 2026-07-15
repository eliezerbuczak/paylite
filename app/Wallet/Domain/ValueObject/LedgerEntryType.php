<?php

declare(strict_types=1);

namespace App\Wallet\Domain\ValueObject;

enum LedgerEntryType: string
{
    case Deposit = 'deposit';
    case Transfer = 'transfer';
}
