<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix bookings table column names to snake_case
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'discountReason')) {
                $table->renameColumn('discountReason', 'discount_reason');
            }
            if (Schema::hasColumn('bookings', 'discountValue')) {
                $table->renameColumn('discountValue', 'discount_value');
            }
            if (Schema::hasColumn('bookings', 'finalPrice')) {
                $table->renameColumn('finalPrice', 'final_price');
            }
        });

        // Add missing columns to booking_promotions
        Schema::table('booking_promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_promotions', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->after('promotion_id');
            }
        });

        // Add missing columns to payments
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'user_id')) {
                $table->uuid('user_id')->after('booking_id');
                $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('payments', 'order_id')) {
                $table->string('order_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('payments', 'txn_ref')) {
                $table->string('txn_ref')->unique()->nullable()->after('order_id');
            }
            if (!Schema::hasColumn('payments', 'payment_url')) {
                $table->text('payment_url')->nullable()->after('txn_ref');
            }
            if (!Schema::hasColumn('payments', 'gateway_response')) {
                $table->json('gateway_response')->nullable()->after('payment_url');
            }
            if (!Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('gateway_response');
            }
        });

        // Add missing columns to refunds
        Schema::table('refunds', function (Blueprint $table) {
            if (!Schema::hasColumn('refunds', 'booking_id')) {
                $table->uuid('booking_id')->after('payment_id');
                $table->foreign('booking_id')->references('booking_id')->on('bookings')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('refunds', 'user_id')) {
                $table->uuid('user_id')->after('booking_id');
                $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('refunds', 'status')) {
                $table->enum('status', ['PENDING', 'COMPLETED', 'FAILED'])->default('PENDING')->after('user_id');
            }
            if (!Schema::hasColumn('refunds', 'currency')) {
                $table->string('currency')->default('VND')->after('amount');
            }
            if (!Schema::hasColumn('refunds', 'gateway_response')) {
                $table->json('gateway_response')->nullable()->after('refund_gateway_txn_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            if (Schema::hasColumn('refunds', 'gateway_response')) {
                $table->dropColumn('gateway_response');
            }
            if (Schema::hasColumn('refunds', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::hasColumn('refunds', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('refunds', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('refunds', 'booking_id')) {
                $table->dropForeign(['booking_id']);
                $table->dropColumn('booking_id');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
            if (Schema::hasColumn('payments', 'gateway_response')) {
                $table->dropColumn('gateway_response');
            }
            if (Schema::hasColumn('payments', 'payment_url')) {
                $table->dropColumn('payment_url');
            }
            if (Schema::hasColumn('payments', 'txn_ref')) {
                $table->dropUnique(['txn_ref']);
                $table->dropColumn('txn_ref');
            }
            if (Schema::hasColumn('payments', 'order_id')) {
                $table->dropColumn('order_id');
            }
            if (Schema::hasColumn('payments', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });

        Schema::table('booking_promotions', function (Blueprint $table) {
            if (Schema::hasColumn('booking_promotions', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'final_price')) {
                $table->renameColumn('final_price', 'finalPrice');
            }
            if (Schema::hasColumn('bookings', 'discount_value')) {
                $table->renameColumn('discount_value', 'discountValue');
            }
            if (Schema::hasColumn('bookings', 'discount_reason')) {
                $table->renameColumn('discount_reason', 'discountReason');
            }
        });
    }
};
