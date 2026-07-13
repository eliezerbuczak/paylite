<?php

declare(strict_types=1);
use App\Middleware\IdempotencyMiddleware;

return [
    'http' => [
        IdempotencyMiddleware::class,
    ],
];
