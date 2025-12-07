<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // ===== PK =====
            $table->uuid('user_id')->primary();

            // ===== Thông tin chính =====
            $table->string('username');
            $table->string('email')->unique();
            $table->string('phoneNumber')->nullable();   // theo đúng schema v2.4
            $table->string('password');
            $table->string('provider')->nullable();      // local, google, facebook...

            // ===== Quyền & avatar =====
            $table->enum('role', ['ADMIN', 'USER', 'GUEST'])->default('USER');
            $table->string('avatar_url')->nullable();
            $table->string('avatar_cloudinary_id')->nullable();

            // ===== Membership / loyalty =====
            $table->integer('loyalty_points')->default(0);
            $table->uuid('membership_tier_id')->nullable(); 
            // FK -> membership_tiers.tier_id (chưa khai báo constraint để khỏi lỗi thứ tự migration)

            // ===== timestamps =====
            $table->timestamps(); // created_at, updated_at (datetime)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
