<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('promotion_id')->primary();  // promotion_id uuid [pk]

            $table->string('code')->unique();         // mã KM
            $table->string('name');                   // tên chương trình
            $table->text('description')->nullable();  // mô tả

            // Enum DiscountType { PERCENT, FIXED_AMOUNT }
            $table->enum('discount_type', ['PERCENT', 'FIXED_AMOUNT']);
            $table->decimal('discount_value', 10, 2);

            $table->dateTime('start_date');
            $table->dateTime('end_date');

            $table->integer('usage_limit')->nullable();    // tổng số lần dùng
            $table->integer('per_user_limit')->nullable(); // giới hạn mỗi user

            $table->boolean('is_active')->default(true);

            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
