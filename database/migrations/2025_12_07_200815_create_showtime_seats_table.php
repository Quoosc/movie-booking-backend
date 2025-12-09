<?php
// database/migrations/2025_12_07_200815_create_showtime_seats_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showtime_seats', function (Blueprint $table) {
            $table->uuid('showtime_seat_id')->primary(); // showtime_seat_id uuid [pk]

            $table->uuid('seat_id');       // ref Seats.seat_id
            $table->uuid('showtime_id');   // ref Showtimes.showtime_id

            // SeatStatus { AVAILABLE, LOCKED, BOOKED }
            $table->enum('seat_status', ['AVAILABLE', 'LOCKED', 'BOOKED'])
                  ->default('AVAILABLE');

            $table->decimal('price', 10, 2)->nullable(); // price decimal
            // price_breakdown varchar  // json string
            $table->text('price_breakdown')->nullable(); // cho rộng hơn varchar(255)

            $table->timestamps();

            $table->foreign('seat_id')
                  ->references('seat_id')
                  ->on('seats')
                  ->onDelete('cascade');

            $table->foreign('showtime_id')
                  ->references('showtime_id')
                  ->on('showtimes')
                  ->onDelete('cascade');

            $table->index(['showtime_id', 'seat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtime_seats');
    }
};
