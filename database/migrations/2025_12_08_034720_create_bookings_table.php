<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
    $table->uuid('booking_id')->primary();
    $table->uuid('user_id')->nullable();
    $table->uuid('showtime_id');
    $table->dateTime('booked_at');
    $table->decimal('total_price', 10, 2)->default(0);
    $table->string('discountReason')->nullable();
    $table->decimal('discountValue', 10, 2)->nullable();
    $table->decimal('finalPrice', 10, 2)->default(0);

    $table->enum('status', [
        'PENDING_PAYMENT',
        'CONFIRMED',
        'CANCELLED',
        'EXPIRED',
        'REFUND_PENDING',
        'REFUNDED',
    ])->default('PENDING_PAYMENT');

    $table->string('qr_code')->nullable();
    $table->text('qr_payload')->nullable();

    $table->dateTime('payment_expires_at')->nullable();
    $table->boolean('loyalty_points_awarded')->default(false);

    $table->boolean('refunded')->default(false);
    $table->dateTime('refunded_at')->nullable();
    $table->string('refund_reason')->nullable();

    $table->timestamps();

    $table->foreign('user_id')
          ->references('user_id')
          ->on('users')
          ->nullOnDelete();

    $table->foreign('showtime_id')
          ->references('showtime_id')
          ->on('showtimes')
          ->onDelete('cascade');

    $table->index(['showtime_id', 'status']);
});

    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
