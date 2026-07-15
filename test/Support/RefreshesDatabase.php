<?php

declare(strict_types=1);

namespace HyperfTest\Support;

use Hyperf\DbConnection\Db;

trait RefreshesDatabase
{
    /**
     * Truncates every application table that already exists, in FK-safe order.
     */
    protected function refreshDatabase(): void
    {
        foreach (['outbox_events', 'deposits', 'transfers', 'wallets', 'users'] as $table) {
            $exists = Db::table('pg_tables')
                ->where('schemaname', 'public')
                ->where('tablename', $table)
                ->exists();

            if ($exists) {
                Db::statement("TRUNCATE TABLE {$table} RESTART IDENTITY CASCADE");
            }
        }
    }
}
