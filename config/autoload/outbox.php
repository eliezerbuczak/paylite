<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'publish' => [
        'batch_size' => (int) env('OUTBOX_PUBLISH_BATCH_SIZE', 50),
        'max_attempts' => (int) env('OUTBOX_PUBLISH_MAX_ATTEMPTS', 10),
        'initial_backoff_seconds' => (int) env('OUTBOX_PUBLISH_INITIAL_BACKOFF_SECONDS', 5),
        'max_backoff_seconds' => (int) env('OUTBOX_PUBLISH_MAX_BACKOFF_SECONDS', 300),
    ],
];
