<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Shared\Outbox\Application;

use App\Shared\Outbox\Application\OutboxPublishSettings;
use App\Shared\Outbox\Application\PublishPendingOutboxEvents;
use App\Shared\Outbox\Domain\Entity\OutboxEvent;
use App\Shared\Outbox\Domain\Exception\OutboxPublishException;
use App\Shared\Outbox\Domain\Gateway\OutboxEventPublisherInterface;
use App\Shared\Outbox\Domain\Repository\OutboxEventRepositoryInterface;
use DateTimeImmutable;
use Hyperf\Logger\LoggerFactory;
use HyperfTest\Support\FakeClock;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
#[CoversClass(PublishPendingOutboxEvents::class)]
class PublishPendingOutboxEventsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_publishes_a_pending_event_and_marks_it_published(): void
    {
        $outbox = Mockery::mock(OutboxEventRepositoryInterface::class);
        $outbox->shouldReceive('claimNext')->once()->andReturn($this->event())->ordered();
        $outbox->shouldReceive('claimNext')->once()->andReturnNull()->ordered();
        $outbox->shouldReceive('markPublished')->once()->with(1);
        $publisher = Mockery::mock(OutboxEventPublisherInterface::class);
        $publisher->shouldReceive('publish')->once()->withArgs(fn (OutboxEvent $event): bool => $event->id === 1);

        $summary = $this->useCase($outbox, $publisher)->run();

        self::assertSame(1, $summary->published);
        self::assertSame(0, $summary->retried);
        self::assertSame(0, $summary->failed);
    }

    public function test_stops_when_there_is_nothing_left_to_claim(): void
    {
        $outbox = Mockery::mock(OutboxEventRepositoryInterface::class);
        $outbox->shouldReceive('claimNext')->once()->andReturnNull();
        $publisher = $this->untouchedPublisher();

        $summary = $this->useCase($outbox, $publisher)->run();

        self::assertSame(0, $summary->published);
        self::assertSame(0, $summary->retried);
        self::assertSame(0, $summary->failed);
    }

    public function test_never_claims_more_than_the_configured_batch_size(): void
    {
        $outbox = Mockery::mock(OutboxEventRepositoryInterface::class);
        $outbox->shouldReceive('claimNext')->times(2)->andReturn($this->event());
        $outbox->shouldReceive('markPublished')->twice();
        $publisher = Mockery::mock(OutboxEventPublisherInterface::class);
        $publisher->shouldReceive('publish')->twice();

        $summary = $this->useCase($outbox, $publisher, batchSize: 2)->run();

        self::assertSame(2, $summary->published);
    }

    public function test_schedules_a_retry_with_backoff_when_publishing_fails(): void
    {
        $outbox = Mockery::mock(OutboxEventRepositoryInterface::class);
        $outbox->shouldReceive('claimNext')->once()->andReturn($this->event(attempts: 1))->ordered();
        $outbox->shouldReceive('claimNext')->once()->andReturnNull()->ordered();
        $outbox->shouldReceive('scheduleRetry')
            ->once()
            ->withArgs(fn (int $id, string $reason, DateTimeImmutable $availableAt): bool => $id === 1
                && $reason === 'broker unreachable'
                // initial backoff 5s doubled per attempt: attempt=1 -> 10s
                && $availableAt->getTimestamp() === (new FakeClock())->now()->getTimestamp() + 10);
        $outbox->shouldNotReceive('markFailed');
        $publisher = Mockery::mock(OutboxEventPublisherInterface::class);
        $publisher->shouldReceive('publish')->once()->andThrow(new OutboxPublishException('broker unreachable'));

        $summary = $this->useCase($outbox, $publisher)->run();

        self::assertSame(0, $summary->published);
        self::assertSame(1, $summary->retried);
        self::assertSame(0, $summary->failed);
    }

    public function test_caps_the_backoff_at_the_configured_maximum(): void
    {
        $outbox = Mockery::mock(OutboxEventRepositoryInterface::class);
        $outbox->shouldReceive('claimNext')->once()->andReturn($this->event(attempts: 9))->ordered();
        $outbox->shouldReceive('claimNext')->once()->andReturnNull()->ordered();
        $outbox->shouldReceive('scheduleRetry')
            ->once()
            ->withArgs(fn (int $id, string $reason, DateTimeImmutable $availableAt): bool => $availableAt->getTimestamp() === (new FakeClock())->now()->getTimestamp() + 60);
        $publisher = Mockery::mock(OutboxEventPublisherInterface::class);
        $publisher->shouldReceive('publish')->once()->andThrow(new OutboxPublishException('still down'));

        $this->useCase($outbox, $publisher, maxAttempts: 50, maxBackoffSeconds: 60)->run();
    }

    public function test_marks_failed_once_max_attempts_is_reached(): void
    {
        $outbox = Mockery::mock(OutboxEventRepositoryInterface::class);
        $outbox->shouldReceive('claimNext')->once()->andReturn($this->event(attempts: 2))->ordered();
        $outbox->shouldReceive('claimNext')->once()->andReturnNull()->ordered();
        $outbox->shouldReceive('markFailed')->once()->with(1, 'broker unreachable');
        $outbox->shouldNotReceive('scheduleRetry');
        $publisher = Mockery::mock(OutboxEventPublisherInterface::class);
        $publisher->shouldReceive('publish')->once()->andThrow(new OutboxPublishException('broker unreachable'));

        $summary = $this->useCase($outbox, $publisher, maxAttempts: 3)->run();

        self::assertSame(0, $summary->published);
        self::assertSame(0, $summary->retried);
        self::assertSame(1, $summary->failed);
    }

    private function useCase(
        OutboxEventRepositoryInterface $outbox,
        OutboxEventPublisherInterface $publisher,
        int $batchSize = 50,
        int $maxAttempts = 10,
        int $initialBackoffSeconds = 5,
        int $maxBackoffSeconds = 300,
    ): PublishPendingOutboxEvents {
        return new PublishPendingOutboxEvents(
            $outbox,
            $publisher,
            new FakeClock(),
            $this->loggerFactory(),
            new OutboxPublishSettings($batchSize, $maxAttempts, $initialBackoffSeconds, $maxBackoffSeconds),
        );
    }

    private function event(int $attempts = 0): OutboxEvent
    {
        return new OutboxEvent(
            id: 1,
            eventType: 'TransferCompleted',
            aggregateType: 'transfer',
            aggregateId: 1,
            payload: ['transfer_id' => 1],
            attempts: $attempts,
        );
    }

    private function loggerFactory(): LoggerFactory
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->zeroOrMoreTimes();
        $factory = Mockery::mock(LoggerFactory::class);
        $factory->shouldReceive('get')->with('outbox')->andReturn($logger);

        return $factory;
    }

    private function untouchedPublisher(): MockInterface&OutboxEventPublisherInterface
    {
        $publisher = Mockery::mock(OutboxEventPublisherInterface::class);
        $publisher->shouldNotReceive('publish');

        return $publisher;
    }
}
