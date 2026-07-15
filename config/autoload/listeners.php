<?php

declare(strict_types=1);
use App\Notification\Infrastructure\Messaging\DeclareNotificationQueuesListener;
use Hyperf\ExceptionHandler\Listener\ErrorExceptionHandler;

return [
    ErrorExceptionHandler::class,
    DeclareNotificationQueuesListener::class,
];
