<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('booking_id');
            $table->uuid('promotion_id');

            $table->dateTime('applied_at');

            $table->timestamps();

            $table->foreign('booking_id')
                  ->references('booking_id')
                  ->on('bookings')
                  ->onDelete('cascade');

            $table->foreign('promotion_id')
                  ->references('promotion_id')
                  ->on('promotions')
                  ->onDelete('cascade');

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_promotions');
    }
};

