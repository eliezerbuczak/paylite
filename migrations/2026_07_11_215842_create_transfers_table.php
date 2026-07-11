<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transfers', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payer_id');
            $table->unsignedBigInteger('payee_id');
            $table->bigInteger('amount_cents');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('payer_id')->references('id')->on('users');
            $table->foreign('payee_id')->references('id')->on('users');

            $table->index('payer_id');
            $table->index('payee_id');
        });

        Db::statement(
            'ALTER TABLE transfers ADD CONSTRAINT transfers_amount_cents_check CHECK (amount_cents > 0)'
        );
        Db::statement(
            'ALTER TABLE transfers ADD CONSTRAINT transfers_payer_payee_check CHECK (payer_id <> payee_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
