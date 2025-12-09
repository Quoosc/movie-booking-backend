<?php
// database/migrations/2025_12_08_032232_create_seat_locks_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_lock_seats', function (Blueprint $table) {
            $table->uuid('id')->primary();        // id uuid pk

            $table->uuid('seat_lock_id');         // ref SeatLocks.seat_lock_id
            $table->uuid('showtime_seat_id');     // ref ShowtimeSeats.showtime_seat_id
            $table->uuid('ticket_type_id');       // ref TicketTypes.id

            $table->decimal('price', 10, 2)->nullable(); // price decimal

            $table->timestamps();

            $table->foreign('seat_lock_id')
                  ->references('seat_lock_id')
                  ->on('seat_locks')
                  ->onDelete('cascade');

            $table->foreign('showtime_seat_id')
                  ->references('showtime_seat_id')
                  ->on('showtime_seats')
                  ->onDelete('cascade');

            $table->foreign('ticket_type_id')
                  ->references('id')
                  ->on('ticket_types')
                  ->onDelete('restrict');

            $table->index(['seat_lock_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_lock_seats');
    }
};