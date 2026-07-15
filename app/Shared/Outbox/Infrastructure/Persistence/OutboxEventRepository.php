<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Infrastructure\Persistence;

use App\Shared\Outbox\Domain\Entity\OutboxEvent;
use App\Shared\Outbox\Domain\Repository\OutboxEventRepositoryInterface;
use App\Shared\Outbox\Domain\ValueObject\OutboxEventStatus;
use App\Shared\Outbox\Infrastructure\Model\OutboxEvent as OutboxEventModel;
use Carbon\Carbon;
use DateTimeImmutable;
use Hyperf\DbConnection\Db;

final class OutboxEventRepository implements OutboxEventRepositoryInterface
{
    /**
     * How long a claimed event is leased before it becomes claimable
     * again. This is what keeps two concurrent publishers from handling
     * the same event, without holding the row's DB lock across the
     * broker call: claimNext() pushes available_at forward and commits
     * immediately, instead of staying inside an open transaction for the
     * duration of the publish. A publisher that crashes mid-flight only
     * blocks the event for this long, not forever — the trade-off is a
     * possible duplicate publish within the lease window, which the
     * notification consumer's idempotency already absorbs.
     */
    private const LEASE_SECONDS = 30;

    public function record(string $eventType, string $aggregateType, int $aggregateId, array $payload): void
    {
        $event = new OutboxEventModel();
        $event->fill([
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
            'status' => OutboxEventStatus::Pending->value,
        ]);
        $event->save();
    }

    public function claimNext(): ?OutboxEvent
    {
        return Db::transaction(static function (): ?OutboxEvent {
            /** @var null|OutboxEventModel $model */
            $model = OutboxEventModel::query()
                ->where('status', OutboxEventStatus::Pending->value)
                ->where('available_at', '<=', Db::raw('now()'))
                ->orderBy('available_at')
                ->orderBy('id')
                ->lock('for update skip locked')
                ->first();

            if ($model === null) {
                return null;
            }

            $model->available_at = Carbon::now('UTC')->addSeconds(self::LEASE_SECONDS);
            $model->save();

            return new OutboxEvent(
                id: $model->id,
                eventType: $model->event_type,
                aggregateType: $model->aggregate_type,
                aggregateId: $model->aggregate_id,
                payload: $model->payload,
                attempts: $model->attempts,
            );
        });
    }

    public function markPublished(int $id): void
    {
        OutboxEventModel::query()->where('id', $id)->update([
            'status' => OutboxEventStatus::Published->value,
            'published_at' => Carbon::now('UTC'),
        ]);
    }

    public function scheduleRetry(int $id, string $reason, DateTimeImmutable $availableAt): void
    {
        OutboxEventModel::query()->where('id', $id)->update([
            'attempts' => Db::raw('attempts + 1'),
            'last_error' => $reason,
            'available_at' => $availableAt,
        ]);
    }

    public function markFailed(int $id, string $reason): void
    {
        OutboxEventModel::query()->where('id', $id)->update([
            'status' => OutboxEventStatus::Failed->value,
            'attempts' => Db::raw('attempts + 1'),
            'last_error' => $reason,
        ]);
    }
}
