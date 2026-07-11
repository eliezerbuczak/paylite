<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallets', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->bigInteger('balance_cents')->default(0);
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users');
        });

        Db::statement(
            'ALTER TABLE wallets ADD CONSTRAINT wallets_balance_cents_check CHECK (balance_cents >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
