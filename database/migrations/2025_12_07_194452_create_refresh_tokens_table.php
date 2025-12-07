<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('token_id')->primary();

            $table->uuid('user_id');                // ref -> users.user_id
            $table->string('token');                // chuỗi token
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();                   // created_at, updated_at

            // FK (được phép vì bảng users đã create trước)
            $table
                ->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
