<?php

declare(strict_types=1);
use App\Notification\Application\EventListener\TransferCompletedListener;
use App\Notification\Infrastructure\Messaging\DeclareNotificationQueuesListener;
use Hyperf\ExceptionHandler\Listener\ErrorExceptionHandler;

return [
    ErrorExceptionHandler::class,
    TransferCompletedListener::class,
    DeclareNotificationQueuesListener::class,
];
