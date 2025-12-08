<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('payment_id')->primary();

            // booking_id string [unique, ref: - Bookings.booking_id]
            $table->uuid('booking_id')->unique();

            $table->string('transaction_id')->nullable();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('VND');

            $table->decimal('gateway_amount', 10, 2)->nullable();
            $table->string('gateway_currency', 10)->nullable();

            $table->decimal('exchange_rate', 10, 4)->nullable();

            $table->enum('status', [
                'PENDING',
                'SUCCESS',
                'FAILED',
                'REFUND_PENDING',
                'REFUNDED',
                'REFUND_FAILED',
            ])->default('PENDING');

            // created_at: thời điểm bắt đầu payment (không dùng timestamps() để khỏi trùng)
            $table->dateTime('created_at');
            $table->dateTime('completed_at')->nullable();

            $table->string('payer_email')->nullable();

            $table->enum('method', ['PAYPAL', 'MOMO']);
            $table->string('error_message')->nullable();

            $table->foreign('booking_id')
                  ->references('booking_id')
                  ->on('bookings')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

