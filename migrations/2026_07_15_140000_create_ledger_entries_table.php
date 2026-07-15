<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_entries', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wallet_id');
            $table->string('direction');
            $table->bigInteger('amount_cents');
            $table->bigInteger('balance_after_cents');
            $table->string('entry_type');
            $table->unsignedBigInteger('related_deposit_id')->nullable();
            $table->unsignedBigInteger('related_transfer_id')->nullable();
            $table->timestampsTz();

            $table->foreign('wallet_id')->references('id')->on('wallets');
            $table->foreign('related_deposit_id')->references('id')->on('deposits');
            $table->foreign('related_transfer_id')->references('id')->on('transfers');

            $table->index('wallet_id');
            $table->index(['entry_type', 'related_transfer_id']);
        });

        Db::statement(
            "ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_direction_check CHECK (direction IN ('credit', 'debit'))"
        );
        Db::statement(
            "ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_entry_type_check CHECK (entry_type IN ('deposit', 'transfer'))"
        );
        Db::statement(
            'ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amount_cents_check CHECK (amount_cents > 0)'
        );
        Db::statement(
            'ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_balance_after_cents_check CHECK (balance_after_cents >= 0)'
        );
        Db::statement(
            <<<'SQL'
            ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_related_id_check CHECK (
                (entry_type = 'deposit' AND related_deposit_id IS NOT NULL AND related_transfer_id IS NULL)
                OR (entry_type = 'transfer' AND related_transfer_id IS NOT NULL AND related_deposit_id IS NULL)
            )
            SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
