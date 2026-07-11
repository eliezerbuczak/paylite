<?php

declare(strict_types=1);

namespace App\Model;

use Carbon\Carbon;

/**
 * @property int $id
 * @property int $wallet_id
 * @property int $amount_cents
 * @property Carbon $created_at
 */
class Deposit extends Model
{
    public const UPDATED_AT = null;

    protected ?string $table = 'deposits';

    /** @var list<string> */
    protected array $fillable = ['wallet_id', 'amount_cents'];

    /** @var array<string, string> */
    protected array $casts = [
        'id' => 'integer',
        'wallet_id' => 'integer',
        'amount_cents' => 'integer',
        'created_at' => 'datetime',
    ];
}
