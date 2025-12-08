<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->uuid('seat_id')->primary();   // seat_id uuid [pk]

            $table->uuid('room_id');              // room_id uuid [ref: > Rooms.room_id]

            $table->string('row_label', 10);      // 'A'
            $table->integer('seat_number');       // 10

            // seat_type SeatType { NORMAL, VIP, COUPLE }
            $table->enum('seat_type', ['NORMAL', 'COUPLE'])->default('NORMAL');

            $table->timestamps();

            $table->foreign('room_id')
                  ->references('room_id')
                  ->on('rooms')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};

