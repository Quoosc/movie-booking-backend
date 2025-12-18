<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize payments table to match current Payment model / enums
        Schema::table('payments', function (Blueprint $table) {

            if (!Schema::hasColumn('payments', 'user_id')) {
                $table->uuid('user_id')->nullable()->after('booking_id');
                $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            }

            if (!Schema::hasColumn('payments', 'order_id')) {
                $table->string('order_id')->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('payments', 'txn_ref')) {
                $table->string('txn_ref')->nullable()->after('order_id');
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

            // Widen error_message to avoid truncation
            if (Schema::hasColumn('payments', 'error_message')) {
                $table->text('error_message')->nullable()->change();
            }
        });

        // Align enums to current PaymentStatus values
        try {
            DB::statement("
                ALTER TABLE payments
                MODIFY status ENUM('PENDING','COMPLETED','FAILED','CANCELLED','REFUND_PENDING','REFUNDED')
                NOT NULL DEFAULT 'PENDING'
            ");
        } catch (\Throwable $e) {
            // ignore if database/driver doesn't support this modification
        }

        // Ensure method enum includes current gateways
        try {
            DB::statement("
                ALTER TABLE payments
                MODIFY method ENUM('PAYPAL','MOMO')
                NOT NULL
            ");
        } catch (\Throwable $e) {
            // ignore if not supported
        }
    }

    public function down(): void
    {
        // No destructive rollback to avoid data loss; keep schema compatible
    }
};
