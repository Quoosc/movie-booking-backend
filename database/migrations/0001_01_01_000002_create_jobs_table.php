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
        Schema::create('users', function (Blueprint $table) {
            // PK dùng UUID
            $table->uuid('user_id')->primary();

            $table->string('username');
            $table->string('email')->unique();
            $table->string('phoneNumber')->nullable();
            $table->string('password');

            // provider: local, google, facebook...
            $table->string('provider')->nullable();

            // Enum role: ADMIN / USER / GUEST
            $table->enum('role', ['ADMIN', 'USER', 'GUEST'])->default('USER');

            $table->string('avatar_url')->nullable();
            $table->string('avatar_cloudinary_id')->nullable();

            $table->integer('loyalty_points')->default(0);

            // Membership tier (nullable, vì user mới có thể chưa có hạng)
            $table->uuid('membership_tier_id')->nullable();

            $table->timestamps();

            // Tạm thời CHƯA tạo foreign key membership_tier_id
            // (sau khi có bảng membership_tiers sẽ làm 1 migration riêng add FK)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
