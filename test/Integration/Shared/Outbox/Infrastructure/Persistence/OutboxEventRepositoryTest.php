<?php

declare(strict_types=1);

namespace HyperfTest\Integration\Shared\Outbox\Infrastructure\Persistence;

use App\Shared\Outbox\Domain\Entity\OutboxEvent;
use App\Shared\Outbox\Domain\Repository\OutboxEventRepositoryInterface;
use App\Shared\Outbox\Domain\ValueObject\OutboxEventStatus;
use App\Shared\Outbox\Infrastructure\Persistence\OutboxEventRepository;
use DateTimeImmutable;
use DateTimeZone;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use HyperfTest\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(OutboxEventRepository::class)]
class OutboxEventRepositoryTest extends IntegrationTestCase
{
    public function test_records_a_pending_event_available_immediately(): void
    {
        $this->repository()->record('TransferCompleted', 'transfer', 123, [
            'transfer_id' => 123,
            'payer_id' => 1,
            'payee_id' => 2,
            'amount_cents' => 3000,
        ]);

        self::assertSame('TransferCompleted', $this->columnOf(123, 'event_type'));
        self::assertSame('transfer', $this->columnOf(123, 'aggregate_type'));
        self::assertSame(OutboxEventStatus::Pending->value, $this->columnOf(123, 'status'));
        self::assertSame(0, (int) $this->columnOf(123, 'attempts'));
        self::assertNull($this->columnOf(123, 'published_at'));
        self::assertEquals(
            ['transfer_id' => 123, 'payer_id' => 1, 'payee_id' => 2, 'amount_cents' => 3000],
            json_decode((string) $this->columnOf(123, 'payload'), true),
            'jsonb does not preserve key order, so compare by content'
        );
    }

    public function test_claims_the_oldest_available_pending_event(): void
    {
        $repository = $this->repository();
        $repository->record('TransferCompleted', 'transfer', 1, ['transfer_id' => 1]);
        $repository->record('TransferCompleted', 'transfer', 2, ['transfer_id' => 2]);

        $claimed = $repository->claimNext();

        self::assertInstanceOf(OutboxEvent::class, $claimed);
        self::assertSame(1, $claimed->aggregateId);
        self::assertSame(['transfer_id' => 1], $claimed->payload);
        self::assertSame(0, $claimed->attempts);
    }

    public function test_does_not_claim_an_event_scheduled_for_the_future(): void
    {
        $repository = $this->repository();
        $repository->record('TransferCompleted', 'transfer', 1, ['transfer_id' => 1]);
        Db::table('outbox_events')->update(['available_at' => Db::raw("now() + interval '1 hour'")]);

        self::assertNull($repository->claimNext());
    }

    public function test_claiming_leases_the_row_so_a_concurrent_publisher_cannot_grab_it_too(): void
    {
        $repository = $this->repository();
        $repository->record('TransferCompleted', 'transfer', 1, ['transfer_id' => 1]);

        $first = $repository->claimNext();
        $second = $repository->claimNext();

        self::assertNotNull($first);
        self::assertNull($second, 'a freshly claimed row must not be claimable again immediately');
    }

    public function test_returns_null_when_there_is_nothing_pending(): void
    {
        self::assertNull($this->repository()->claimNext());
    }

    public function test_marks_an_event_published(): void
    {
        $repository = $this->repository();
        $repository->record('TransferCompleted', 'transfer', 1, ['transfer_id' => 1]);
        $id = $this->idOf(1);

        $repository->markPublished($id);

        self::assertSame(OutboxEventStatus::Published->value, $this->columnOf(1, 'status'));
        self::assertNotNull($this->columnOf(1, 'published_at'));
    }

    public function test_schedules_a_retry_recording_the_failure_reason(): void
    {
        $repository = $this->repository();
        $repository->record('TransferCompleted', 'transfer', 1, ['transfer_id' => 1]);
        $id = $this->idOf(1);
        // Anchored to UTC, matching what ClockInterface implementations
        // hand the real caller (PublishPendingOutboxEvents) — the test
        // process itself runs in America/Sao_Paulo on purpose.
        $nextAttempt = new DateTimeImmutable('+30 seconds', new DateTimeZone('UTC'));

        $repository->scheduleRetry($id, 'broker unreachable', $nextAttempt);

        self::assertSame(OutboxEventStatus::Pending->value, $this->columnOf(1, 'status'));
        self::assertSame(1, (int) $this->columnOf(1, 'attempts'));
        self::assertSame('broker unreachable', $this->columnOf(1, 'last_error'));
        self::assertEqualsWithDelta(
            $nextAttempt->getTimestamp(),
            (new DateTimeImmutable((string) $this->columnOf(1, 'available_at')))->getTimestamp(),
            1
        );
    }

    public function test_marks_an_event_failed_after_exhausting_retries(): void
    {
        $repository = $this->repository();
        $repository->record('TransferCompleted', 'transfer', 1, ['transfer_id' => 1]);
        $id = $this->idOf(1);

        $repository->markFailed($id, 'gave up after max attempts');

        self::assertSame(OutboxEventStatus::Failed->value, $this->columnOf(1, 'status'));
        self::assertSame(1, (int) $this->columnOf(1, 'attempts'));
        self::assertSame('gave up after max attempts', $this->columnOf(1, 'last_error'));
    }

    private function idOf(int $aggregateId): int
    {
        return (int) $this->columnOf($aggregateId, 'id');
    }

    private function columnOf(int $aggregateId, string $column): mixed
    {
        return Db::table('outbox_events')->where('aggregate_id', $aggregateId)->value($column);
    }

    private function repository(): OutboxEventRepositoryInterface
    {
        return ApplicationContext::getContainer()->get(OutboxEventRepositoryInterface::class);
    }
}
