<?php

declare(strict_types=1);
use App\Shared\Infrastructure\Middleware\IdempotencyMiddleware;

return [
    'http' => [
        IdempotencyMiddleware::class,
    ],
];
