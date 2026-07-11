<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('full_name');
            $table->string('document', 14)->unique();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('type', 10);
            $table->timestampsTz();
        });

        Db::statement(
            "ALTER TABLE users ADD CONSTRAINT users_type_check CHECK (type IN ('common', 'merchant'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
