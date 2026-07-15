<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Infrastructure\Command;

use App\Shared\Outbox\Application\PublishPendingOutboxEvents;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

#[Command]
final class PublishOutboxEventsCommand extends HyperfCommand
{
    public function __construct(
        private readonly PublishPendingOutboxEvents $useCase,
    ) {
        parent::__construct('outbox:publish');
        $this->setDescription('Publishes pending outbox events to the message broker');
    }

    public function handle(): void
    {
        $summary = $this->useCase->run();

        $this->line(sprintf(
            'outbox:publish published=%d retried=%d failed=%d',
            $summary->published,
            $summary->retried,
            $summary->failed,
        ));
    }
}
