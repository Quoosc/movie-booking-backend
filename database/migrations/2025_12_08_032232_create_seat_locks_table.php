<?php
// database/migrations/2025_12_08_032232_create_seat_locks_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_locks', function (Blueprint $table) {
            $table->uuid('seat_lock_id')->primary(); // seat_lock_id uuid [pk]

            $table->uuid('lock_owner_id'); // guest session id hoặc user id (tùy loại)
            // LockOwnerType { USER, GUEST_SESSION }
            $table->enum('lock_owner_type', ['USER', 'GUEST_SESSION']);

            $table->uuid('user_id')->nullable();   // ref Users.user_id (nullable)
            $table->uuid('showtime_id');           // ref Showtimes.showtime_id

            $table->string('lock_key', 100);       // token lock (UUID string)

            // created_at, expires_at theo spec
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');

            $table->boolean('active')->default(true);

            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->nullOnDelete();

            $table->foreign('showtime_id')
                  ->references('showtime_id')
                  ->on('showtimes')
                  ->onDelete('cascade');

            $table->index(['showtime_id', 'active']);
            $table->index(['lock_owner_type', 'lock_owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_locks');
    }
};
