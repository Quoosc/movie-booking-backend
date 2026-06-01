<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('review_id')->index();
            $table->string('reaction_type', 10); // LIKE, LOVE, HAHA, WOW, SAD
            $table->uuid('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->foreign('review_id')->references('review_id')->on('movie_reviews')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reactions');
    }
};
