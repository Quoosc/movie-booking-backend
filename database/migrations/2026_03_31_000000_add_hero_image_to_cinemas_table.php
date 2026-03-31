<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cinemas', function (Blueprint $table) {
            $table->text('hero_image_url')->nullable()->after('hotline');
            $table->string('hero_image_cloudinary_id')->nullable()->after('hero_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('cinemas', function (Blueprint $table) {
            $table->dropColumn(['hero_image_url', 'hero_image_cloudinary_id']);
        });
    }
};
