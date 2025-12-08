<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_lock_seats', function (Blueprint $table) {
            // PK
            $table->uuid('id')->primary();

            $table->uuid('seat_lock_id');
            $table->uuid('showtime_seat_id');
            $table->uuid('ticket_type_id');

            $table->decimal('price', 10, 2)->default(0);

            // FK
            $table->foreign('seat_lock_id')
                ->references('seat_lock_id')->on('seat_locks')
                ->onDelete('cascade');

            $table->foreign('showtime_seat_id')
                ->references('showtime_seat_id')->on('showtime_seats')
                ->onDelete('cascade');

            $table->foreign('ticket_type_id')
                ->references('id')->on('ticket_types')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_lock_seats');
    }
};
