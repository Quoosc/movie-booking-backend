<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // STEP 1: Update data in payments table (SUCCESS → COMPLETED, REFUND_FAILED → FAILED)
        DB::statement("UPDATE payments SET status = 'COMPLETED' WHERE status = 'SUCCESS'");
        DB::statement("UPDATE payments SET status = 'FAILED' WHERE status = 'REFUND_FAILED'");

        // STEP 2: Update data in bookings table (REFUNDED → CANCELLED, REFUND_PENDING → CANCELLED)
        DB::statement("UPDATE bookings SET status = 'CANCELLED' WHERE status = 'REFUNDED'");
        DB::statement("UPDATE bookings SET status = 'CANCELLED' WHERE status = 'REFUND_PENDING'");

        // STEP 3: Modify payments table enum definition
        DB::statement("
            ALTER TABLE payments 
            MODIFY COLUMN status ENUM(
                'PENDING',
                'COMPLETED',
                'FAILED',
                'REFUND_PENDING',
                'REFUNDED',
                'CANCELLED'
            ) DEFAULT 'PENDING'
        ");

        // STEP 4: Modify bookings table enum definition
        DB::statement("
            ALTER TABLE bookings 
            MODIFY COLUMN status ENUM(
                'PENDING_PAYMENT',
                'CONFIRMED',
                'CANCELLED',
                'EXPIRED'
            ) DEFAULT 'PENDING_PAYMENT'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse data changes
        DB::statement("UPDATE payments SET status = 'SUCCESS' WHERE status = 'COMPLETED'");
        DB::statement("UPDATE payments SET status = 'REFUND_FAILED' WHERE status = 'FAILED'");
        DB::statement("UPDATE bookings SET status = 'REFUNDED' WHERE status = 'CANCELLED' AND refunded = 1");

        // Restore old enum definitions
        DB::statement("
            ALTER TABLE payments 
            MODIFY COLUMN status ENUM(
                'PENDING',
                'SUCCESS',
                'FAILED',
                'REFUND_PENDING',
                'REFUNDED',
                'REFUND_FAILED'
            ) DEFAULT 'PENDING'
        ");

        DB::statement("
            ALTER TABLE bookings 
            MODIFY COLUMN status ENUM(
                'PENDING_PAYMENT',
                'CONFIRMED',
                'CANCELLED',
                'EXPIRED',
                'REFUND_PENDING',
                'REFUNDED'
            ) DEFAULT 'PENDING_PAYMENT'
        ");
    }
};
