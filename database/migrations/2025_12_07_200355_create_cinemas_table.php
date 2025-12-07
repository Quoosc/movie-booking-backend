<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cinemas', function (Blueprint $table) {
            $table->uuid('cinema_id')->primary();
            $table->string('name');
            $table->string('address');
            $table->string('hotline'); // theo ERD: không nullable

            // CinemaStatus: lưu dạng string cho đúng kiểu varchar trong ERD
            $table->string('status', 30); 

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cinemas');
    }
};
