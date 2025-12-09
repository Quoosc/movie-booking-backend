<?php
// database/migrations/2025_12_07_200751_create_showtimes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showtimes', function (Blueprint $table) {
            $table->uuid('showtime_id')->primary();

            $table->uuid('room_id');
            $table->uuid('movie_id');

            $table->string('format');
            $table->dateTime('start_time');

            $table->timestamps();

            $table->foreign('room_id')
                  ->references('room_id')
                  ->on('rooms')
                  ->onDelete('cascade');

            $table->foreign('movie_id')
                  ->references('movie_id')
                  ->on('movies')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtimes');
    }
};
