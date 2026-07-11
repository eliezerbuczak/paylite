<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deposits', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wallet_id');
            $table->bigInteger('amount_cents');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('wallet_id')->references('id')->on('wallets');

            $table->index('wallet_id');
        });

        Db::statement(
            'ALTER TABLE deposits ADD CONSTRAINT deposits_amount_cents_check CHECK (amount_cents > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
