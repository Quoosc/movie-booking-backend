<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movie_reviews', function (Blueprint $table) {
            $table->uuid('parent_id')->nullable()->after('review_id');
            $table->foreign('parent_id')->references('review_id')->on('movie_reviews')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('movie_reviews', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
