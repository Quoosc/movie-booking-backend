<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_tiers', function (Blueprint $table) {
            $table->uuid('tier_id')->primary();
            $table->string('name'); // SILVER, GOLD, PLATINUM
            $table->integer('min_points');

            // discount_type: PERCENT / FIXED_AMOUNT / null
            $table->enum('discount_type', ['PERCENT', 'FIXED_AMOUNT'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();

            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_tiers');
    }
};
