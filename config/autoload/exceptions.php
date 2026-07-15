<?php

declare(strict_types=1);
use App\Shared\Exception\Handler\AppExceptionHandler;
use App\Shared\Exception\Handler\HttpErrorExceptionHandler;
use Hyperf\HttpServer\Exception\Handler\HttpExceptionHandler;

return [
    'handler' => [
        'http' => [
            HttpExceptionHandler::class,
            HttpErrorExceptionHandler::class,
            AppExceptionHandler::class,
        ],
    ],
];
