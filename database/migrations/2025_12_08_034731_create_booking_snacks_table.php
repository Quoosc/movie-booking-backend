<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_snacks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('booking_id');
            $table->uuid('snack_id');
            $table->integer('quantity');

            $table->timestamps();

            $table->foreign('booking_id')
                  ->references('booking_id')
                  ->on('bookings')
                  ->onDelete('cascade');

            $table->foreign('snack_id')
                  ->references('snack_id')
                  ->on('snacks')
                  ->onDelete('cascade');

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_snacks');
    }
};
