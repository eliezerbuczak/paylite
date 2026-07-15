<?php

declare(strict_types=1);
use App\Listener\DeclareNotificationQueuesListener;
use App\Listener\TransferCompletedListener;
use Hyperf\ExceptionHandler\Listener\ErrorExceptionHandler;

return [
    ErrorExceptionHandler::class,
    TransferCompletedListener::class,
    DeclareNotificationQueuesListener::class,
];
