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

            $table->uuid('cinema_id');
            $table->string('room_type');
            $table->integer('room_number');

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
