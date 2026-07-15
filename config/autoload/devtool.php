<?php

declare(strict_types=1);
// Hyperf's `gen:*` scaffolding commands are not module-aware; this project
// writes code by hand per the tdd/php-standards skills instead. These
// namespaces exist only so an accidental `gen:*` run lands in a real,
// existing directory instead of silently recreating the old flat layout
// (app/Controller, app/Listener, ...) this codebase moved away from —
// relocate any generated file into the right module by hand.
return [
    'generator' => [
        'amqp' => [
            'consumer' => [
                'namespace' => 'App\Shared\Infrastructure\Messaging\Consumer',
            ],
            'producer' => [
                'namespace' => 'App\Shared\Infrastructure\Messaging\Producer',
            ],
        ],
        'aspect' => [
            'namespace' => 'App\Shared\Infrastructure\Aspect',
        ],
        'command' => [
            'namespace' => 'App\Shared\Infrastructure\Command',
        ],
        'controller' => [
            'namespace' => 'App\Shared\Infrastructure\Http',
        ],
        'job' => [
            'namespace' => 'App\Shared\Infrastructure\Job',
        ],
        'listener' => [
            'namespace' => 'App\Shared\Infrastructure\Listener',
        ],
        'middleware' => [
            'namespace' => 'App\Shared\Infrastructure\Middleware',
        ],
        'Process' => [
            'namespace' => 'App\Shared\Infrastructure\Process',
        ],
    ],
];
