<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outbox_events', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('event_type');
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id');
            $table->jsonb('payload');
            $table->string('status')->default('pending');
            $table->integer('attempts')->default(0);
            // Microsecond precision on purpose: the claim query compares
            // this against now() moments after insert/update, and the
            // default precision-0 timestamp rounds to the nearest second —
            // which can round up past a same-instant now() and make a
            // freshly available event invisible to claimNext() for up to
            // ~1s.
            $table->timestampTz('available_at', 6)->useCurrent();
            $table->timestampTz('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'available_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index('event_type');
        });

        Db::statement(
            "ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_status_check CHECK (status IN ('pending', 'published', 'failed'))"
        );
        Db::statement(
            'ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_attempts_check CHECK (attempts >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
