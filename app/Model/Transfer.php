<?php

declare(strict_types=1);

namespace App\Model;

use App\Shared\Infrastructure\Model\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $payer_id
 * @property int $payee_id
 * @property int $amount_cents
 * @property Carbon $created_at
 */
class Transfer extends Model
{
    public const UPDATED_AT = null;

    protected ?string $table = 'transfers';

    /** @var list<string> */
    protected array $fillable = ['payer_id', 'payee_id', 'amount_cents'];

    /** @var array<string, string> */
    protected array $casts = [
        'id' => 'integer',
        'payer_id' => 'integer',
        'payee_id' => 'integer',
        'amount_cents' => 'integer',
        'created_at' => 'datetime',
    ];
}
