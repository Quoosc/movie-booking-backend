<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movie_reviews', function (Blueprint $table) {
            $table->boolean('is_spoiler')->default(false)->after('comment');
            $table->string('status', 20)->default('PUBLISHED')->after('is_spoiler')->index();
            $table->timestamp('edited_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('movie_reviews', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['is_spoiler', 'status', 'edited_at']);
        });
    }
};
