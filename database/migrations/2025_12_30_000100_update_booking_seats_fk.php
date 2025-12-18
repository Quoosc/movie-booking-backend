<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prevent booking_seats from being deleted when seat_lock_seats are deleted.
        Schema::table('booking_seats', function () {
            // Drop existing FK (likely ON DELETE CASCADE)
            DB::statement('ALTER TABLE booking_seats DROP FOREIGN KEY booking_seats_seat_lock_seat_id_foreign');
            // Allow nulls so SET NULL works
            DB::statement('ALTER TABLE booking_seats MODIFY seat_lock_seat_id char(36) NULL');
            // Re-add FK with ON DELETE SET NULL
            DB::statement('ALTER TABLE booking_seats ADD CONSTRAINT booking_seats_seat_lock_seat_id_foreign FOREIGN KEY (seat_lock_seat_id) REFERENCES seat_lock_seats(id) ON DELETE SET NULL');
        });
    }

    public function down(): void
    {
        Schema::table('booking_seats', function () {
            DB::statement('ALTER TABLE booking_seats DROP FOREIGN KEY booking_seats_seat_lock_seat_id_foreign');
            DB::statement('ALTER TABLE booking_seats MODIFY seat_lock_seat_id char(36) NOT NULL');
            DB::statement('ALTER TABLE booking_seats ADD CONSTRAINT booking_seats_seat_lock_seat_id_foreign FOREIGN KEY (seat_lock_seat_id) REFERENCES seat_lock_seats(id) ON DELETE CASCADE');
        });
    }
};
