<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code');   // adult, student, member, double...
            $table->string('label');  // sửa lỗi lavel -> label

            // Theo ERD: modifier_type là varchar, comment: PERCENT / FIXED
            $table->string('modifier_type'); // không ép enum để đồng bộ v2.4
            $table->decimal('modifier_value', 10, 2);

            $table->boolean('active')->default(true);
            $table->integer('sorted_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
