<?php

declare(strict_types=1);

namespace App\Model;

use Carbon\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $balance_cents
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Wallet extends Model
{
    protected ?string $table = 'wallets';

    /** @var list<string> */
    protected array $fillable = ['user_id', 'balance_cents'];

    /** @var array<string, string> */
    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'balance_cents' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
