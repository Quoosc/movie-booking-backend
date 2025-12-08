<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('refund_id')->primary();

            $table->uuid('payment_id');

            $table->decimal('amount', 10, 2);

            $table->string('refund_method');           // Momo, Paypal...
            $table->string('refund_gateway_txn_id')->nullable();

            $table->dateTime('created_at');
            $table->dateTime('refunded_at')->nullable();

            $table->string('reason')->nullable();

            $table->foreign('payment_id')
                  ->references('payment_id')
                  ->on('payments')
                  ->onDelete('cascade');

            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};