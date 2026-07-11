<?php

declare(strict_types=1);

namespace App\Model;

use Carbon\Carbon;

/**
 * @property int $id
 * @property string $full_name
 * @property string $document
 * @property string $email
 * @property string $password_hash
 * @property string $type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class User extends Model
{
    protected ?string $table = 'users';

    /** @var list<string> */
    protected array $fillable = ['full_name', 'document', 'email', 'password_hash', 'type'];

    /** @var list<string> */
    protected array $hidden = ['password_hash'];

    /** @var array<string, string> */
    protected array $casts = [
        'id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
