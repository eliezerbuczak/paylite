<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Application;

enum OutboxPublishOutcome
{
    case Published;
    case Retried;
    case Failed;
}
