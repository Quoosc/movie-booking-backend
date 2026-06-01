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
        Schema::create('movie_reviews', function (Blueprint $table) {
            $table->uuid('review_id')->primary();
            $table->uuid('movie_id')->index();
            $table->uuid('user_id')->nullable()->index();
            $table->string('reviewer_name', 120)->nullable();
            $table->tinyInteger('rating')->unsigned();  // 1–5
            $table->text('comment');
            $table->string('image_url', 500)->nullable();
            $table->string('video_url', 500)->nullable();
            $table->timestamps();

            $table->foreign('movie_id')->references('movie_id')->on('movies')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_reviews');
    }
};
