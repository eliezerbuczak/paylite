<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Shared\Outbox\Infrastructure\Command;

use App\Shared\Outbox\Application\OutboxPublishSettings;
use App\Shared\Outbox\Application\PublishPendingOutboxEvents;
use App\Shared\Outbox\Domain\Entity\OutboxEvent;
use App\Shared\Outbox\Domain\Gateway\OutboxEventPublisherInterface;
use App\Shared\Outbox\Domain\Repository\OutboxEventRepositoryInterface;
use App\Shared\Outbox\Infrastructure\Command\PublishOutboxEventsCommand;
use Hyperf\Logger\LoggerFactory;
use HyperfTest\Support\FakeClock;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
#[CoversClass(PublishOutboxEventsCommand::class)]
class PublishOutboxEventsCommandTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_is_named_outbox_publish(): void
    {
        $command = $this->command($this->untouchedOutbox());

        self::assertSame('outbox:publish', $command->getName());
    }

    public function test_delegates_to_the_use_case_and_does_not_blow_up_when_idle(): void
    {
        $outbox = Mockery::mock(OutboxEventRepositoryInterface::class);
        $outbox->shouldReceive('claimNext')->once()->andReturnNull();
        $command = $this->command($outbox);

        $command->handle();

        $this->addToAssertionCount(1);
    }

    public function test_runs_the_batch_to_completion(): void
    {
        $outbox = Mockery::mock(OutboxEventRepositoryInterface::class);
        $outbox->shouldReceive('claimNext')->once()->andReturn(new OutboxEvent(
            id: 1,
            eventType: 'TransferCompleted',
            aggregateType: 'transfer',
            aggregateId: 1,
            payload: ['transfer_id' => 1],
            attempts: 0,
        ))->ordered();
        $outbox->shouldReceive('claimNext')->once()->andReturnNull()->ordered();
        $outbox->shouldReceive('markPublished')->once()->with(1);
        $publisher = Mockery::mock(OutboxEventPublisherInterface::class);
        $publisher->shouldReceive('publish')->once();

        $this->command($outbox, $publisher)->handle();

        $this->addToAssertionCount(1);
    }

    private function command(
        OutboxEventRepositoryInterface $outbox,
        ?OutboxEventPublisherInterface $publisher = null,
    ): PublishOutboxEventsCommand {
        $useCase = new PublishPendingOutboxEvents(
            $outbox,
            $publisher ?? $this->untouchedPublisher(),
            new FakeClock(),
            $this->loggerFactory(),
            new OutboxPublishSettings(batchSize: 50, maxAttempts: 10, initialBackoffSeconds: 5, maxBackoffSeconds: 300),
        );

        return new PublishOutboxEventsCommand($useCase);
    }

    private function loggerFactory(): LoggerFactory
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->zeroOrMoreTimes();
        $factory = Mockery::mock(LoggerFactory::class);
        $factory->shouldReceive('get')->with('outbox')->andReturn($logger);

        return $factory;
    }

    private function untouchedOutbox(): OutboxEventRepositoryInterface
    {
        $outbox = Mockery::mock(OutboxEventRepositoryInterface::class);
        $outbox->shouldNotReceive('claimNext');

        return $outbox;
    }

    private function untouchedPublisher(): OutboxEventPublisherInterface
    {
        $publisher = Mockery::mock(OutboxEventPublisherInterface::class);
        $publisher->shouldNotReceive('publish');

        return $publisher;
    }
}
