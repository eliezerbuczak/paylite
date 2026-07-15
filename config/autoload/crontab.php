<?php

declare(strict_types=1);

use Hyperf\Crontab\Crontab;

use function Hyperf\Support\env;

return [
    'enable' => (bool) env('CRONTAB_ENABLE', true),
    'crontab' => [
        (new Crontab())
            ->setName('outbox:publish')
            ->setRule((string) env('OUTBOX_PUBLISH_CRON', '*/5 * * * * *'))
            ->setType('command')
            ->setCallback(['outbox:publish'])
            // Redis-backed: a run that's still going skips the next tick
            // instead of overlapping (singleton), and only one app
            // replica executes a given tick (onOneServer) — the claim
            // mechanism in OutboxEventRepository is already safe under
            // concurrent publishers, but there's no reason to pay for
            // redundant empty polls across replicas.
            ->setSingleton(true)
            ->setOnOneServer(true)
            ->setMemo('Publishes pending transactional outbox events to RabbitMQ.'),
    ],
];
