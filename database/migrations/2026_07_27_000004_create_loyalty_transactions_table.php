<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->uuid('loyalty_transaction_id')->primary();
            $table->uuid('user_id');
            $table->string('type', 30)->index();
            $table->integer('points');
            $table->unsignedInteger('balance_after');
            $table->string('source_type', 50)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        $now = now();
        DB::table('users')->where('loyalty_points', '>', 0)->orderBy('user_id')->chunk(500, function ($users) use ($now) {
            DB::table('loyalty_transactions')->insert($users->map(fn ($user) => [
                'loyalty_transaction_id' => (string) Str::uuid(),
                'user_id' => $user->user_id,
                'type' => 'OPENING_BALANCE',
                'points' => (int) $user->loyalty_points,
                'balance_after' => (int) $user->loyalty_points,
                'source_type' => 'MIGRATION',
                'source_id' => null,
                'description' => 'Số dư điểm trước khi kích hoạt lịch sử giao dịch',
                'expires_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
