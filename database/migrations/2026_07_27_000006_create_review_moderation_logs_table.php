<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_moderation_logs', function (Blueprint $table) {
            $table->uuid('moderation_log_id')->primary();
            $table->uuid('review_id');
            $table->uuid('moderator_id')->nullable();
            $table->string('action', 20);
            $table->string('previous_status', 20);
            $table->string('next_status', 20);
            $table->text('reason');
            $table->timestamps();

            $table->foreign('review_id')->references('review_id')->on('movie_reviews')->cascadeOnDelete();
            $table->foreign('moderator_id')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['review_id', 'created_at']);
            $table->index(['moderator_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_moderation_logs');
    }
};
