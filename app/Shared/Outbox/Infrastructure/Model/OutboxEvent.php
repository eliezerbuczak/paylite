<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Infrastructure\Model;

use App\Shared\Infrastructure\Model\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string $event_type
 * @property string $aggregate_type
 * @property int $aggregate_id
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property Carbon $available_at
 * @property null|Carbon $published_at
 * @property null|string $last_error
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OutboxEvent extends Model
{
    protected ?string $table = 'outbox_events';

    /** @var list<string> */
    protected array $fillable = [
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'attempts',
        'available_at',
        'published_at',
        'last_error',
    ];

    /** @var array<string, string> */
    protected array $casts = [
        'id' => 'integer',
        'aggregate_id' => 'integer',
        'payload' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
