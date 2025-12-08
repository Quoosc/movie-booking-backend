<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_locks', function (Blueprint $table) {
            // PK
            $table->uuid('seat_lock_id')->primary();

            // DB v2.4
            $table->uuid('lock_owner_id'); // user_id hoặc guest_session_id
            $table->enum('lock_owner_type', ['USER', 'GUEST_SESSION']);

            $table->uuid('user_id')->nullable(); // nullable
            $table->uuid('showtime_id');

            $table->string('lock_key'); // token lock duy nhất

            $table->dateTime('created_at');
            $table->dateTime('expires_at');
            $table->boolean('active')->default(true);

            // FK
            $table->foreign('user_id')
                ->references('user_id')->on('users')
                ->onDelete('cascade');

            $table->foreign('showtime_id')
                ->references('showtime_id')->on('showtimes')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_lock_seats'); // phải drop con trước
        Schema::dropIfExists('seat_locks');
    }
};
