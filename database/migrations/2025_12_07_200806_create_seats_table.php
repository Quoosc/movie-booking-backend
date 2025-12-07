<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->uuid('seat_id')->primary();

            $table->uuid('room_id');

            $table->string('row_label');   // 'A'
            $table->integer('seat_number'); // 10

            $table->enum('seat_type', ['NORMAL', 'VIP', 'COUPLE'])->default('NORMAL');

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
