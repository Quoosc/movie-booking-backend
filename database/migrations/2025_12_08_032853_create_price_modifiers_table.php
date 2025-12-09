<?php
// database/migrations/2025_12_08_032853_create_price_modifiers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_modifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();      // id uuid [pk]

            $table->string('name');             // tên modifier
            $table->string('condition_type');   // 'DAY_OF_WEEK', 'SEAT_TYPE', 'SHOWTIME_FORMAT', ...
            $table->string('condition_value');  // 'WEEKEND', 'VIP', '2D', ...

            // để string cho linh hoạt, FE đang dùng "PERCENTAGE" / "FIXED_AMOUNT"
            $table->string('modifier_type', 50); // 'PERCENTAGE' / 'FIXED_AMOUNT'
            $table->decimal('modifier_value', 10, 2);

            $table->boolean('is_active')->default(true);
            $table->timestamps();               // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_modifiers');
    }
};
