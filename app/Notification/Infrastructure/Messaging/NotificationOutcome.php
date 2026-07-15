<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Messaging;

enum NotificationOutcome
{
    case Delivered;
    case Duplicate;
    case RetryLater;
    case GiveUp;
}
