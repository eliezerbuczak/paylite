<?php

declare(strict_types=1);

namespace App\Wallet\Domain\ValueObject;

enum LedgerEntryDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
