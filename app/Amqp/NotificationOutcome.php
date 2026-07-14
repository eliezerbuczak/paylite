<?php

declare(strict_types=1);

namespace App\Amqp;

enum NotificationOutcome
{
    case Delivered;
    case Duplicate;
    case RetryLater;
    case GiveUp;
}
