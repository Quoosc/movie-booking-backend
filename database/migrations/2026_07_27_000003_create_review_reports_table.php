<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reports', function (Blueprint $table) {
            $table->uuid('report_id')->primary();
            $table->uuid('review_id');
            $table->uuid('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('reason', 30);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('PENDING')->index();
            $table->timestamps();

            $table->foreign('review_id')->references('review_id')->on('movie_reviews')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['review_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
    }
};
