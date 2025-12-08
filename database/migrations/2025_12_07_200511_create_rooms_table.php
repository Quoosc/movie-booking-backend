<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('room_id')->primary();

            $table->uuid('cinema_id');           // FK tới cinemas.cinema_id
            $table->integer('room_number');      // Phòng 1, 2, 3...
            $table->string('room_type');         // STANDARD, IMAX, 3D...

            // ===== Thêm cho đúng spec =====
            $table->integer('capacity')->nullable();     // Số ghế
            $table->boolean('is_active')->default(true); // Phòng còn dùng?

            $table->timestamps();

            $table->foreign('cinema_id')
                  ->references('cinema_id')
                  ->on('cinemas')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
