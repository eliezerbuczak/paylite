<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'url' => env('NOTIFIER_URL', 'https://util.devi.tools/api/v1/notify'),
    'connect_timeout' => 2.0,
    'timeout' => 3.0,
    'breaker' => [
        'failure_threshold' => (int) env('NOTIFIER_BREAKER_FAILURE_THRESHOLD', 5),
        'cooldown_seconds' => (int) env('NOTIFIER_BREAKER_COOLDOWN_SECONDS', 10),
    ],
];
