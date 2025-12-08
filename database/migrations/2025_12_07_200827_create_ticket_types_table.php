<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->uuid('id')->primary(); // id uuid [pk]

            $table->string('code', 50);    // code varchar
            // db v2.4 ghi 'lavel' nhưng rõ ràng là 'label' -> mình sửa cho đúng nghĩa
            $table->string('label', 100);  // Display label: NGƯỜI LỚN, HSSV...

            // modifier_type varchar  // PERCENT / FIXED (v2.4)
            // Để linh hoạt, dùng string, logic FE/BE sẽ kiểm soát giá trị.
            $table->string('modifier_type', 50); // PERCENT / FIXED / FIXED_AMOUNT...

            // modifier_value decimal(10,2)
            $table->decimal('modifier_value', 10, 2)->default(0);

            $table->boolean('active')->default(true);
            $table->integer('sorted_order')->default(0);

            // created_at, updated_at
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
