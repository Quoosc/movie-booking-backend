<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_seats', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('booking_id');
            $table->uuid('showtime_seat_id');
            $table->uuid('seat_lock_seat_id');
            $table->uuid('ticket_type_id');

            $table->decimal('price', 10, 2);

            $table->timestamps();

            $table->foreign('booking_id')
                  ->references('booking_id')
                  ->on('bookings')
                  ->onDelete('cascade');

            $table->foreign('showtime_seat_id')
                  ->references('showtime_seat_id')
                  ->on('showtime_seats')
                  ->onDelete('cascade');

            $table->foreign('seat_lock_seat_id')
                  ->references('id')
                  ->on('seat_lock_seats')
                  ->onDelete('cascade');

            $table->foreign('ticket_type_id')
                  ->references('id')
                  ->on('ticket_types')
                  ->onDelete('restrict');

            $table->index(['booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_seats');
    }
};
