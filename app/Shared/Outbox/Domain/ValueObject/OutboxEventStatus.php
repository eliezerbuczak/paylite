<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Domain\ValueObject;

enum OutboxEventStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Failed = 'failed';
}
