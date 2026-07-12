<?php

declare(strict_types=1);
use App\Exception\Handler\AppExceptionHandler;
use App\Exception\Handler\HttpErrorExceptionHandler;
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
