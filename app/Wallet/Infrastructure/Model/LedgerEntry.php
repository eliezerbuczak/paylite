<?php

declare(strict_types=1);

namespace App\Wallet\Infrastructure\Model;

use App\Shared\Infrastructure\Model\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $wallet_id
 * @property string $direction
 * @property int $amount_cents
 * @property int $balance_after_cents
 * @property string $entry_type
 * @property null|int $related_deposit_id
 * @property null|int $related_transfer_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LedgerEntry extends Model
{
    protected ?string $table = 'ledger_entries';

    /** @var list<string> */
    protected array $fillable = [
        'wallet_id',
        'direction',
        'amount_cents',
        'balance_after_cents',
        'entry_type',
        'related_deposit_id',
        'related_transfer_id',
    ];

    /** @var array<string, string> */
    protected array $casts = [
        'id' => 'integer',
        'wallet_id' => 'integer',
        'amount_cents' => 'integer',
        'balance_after_cents' => 'integer',
        'related_deposit_id' => 'integer',
        'related_transfer_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
