<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showtime_seats', function (Blueprint $table) {
            $table->uuid('showtime_seat_id')->primary();

            $table->uuid('seat_id');
            $table->uuid('showtime_id');

            $table->enum('seat_status', ['AVAILABLE', 'LOCKED', 'BOOKED'])
                  ->default('AVAILABLE');

            $table->decimal('price', 10, 2);
            $table->text('price_breakdown')->nullable(); // json string

            $table->timestamps();

            $table->foreign('seat_id')
                  ->references('seat_id')
                  ->on('seats')
                  ->onDelete('cascade');

            $table->foreign('showtime_id')
                  ->references('showtime_id')
                  ->on('showtimes')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtime_seats');
    }
};
