<?php
// database/migrations/2025_12_08_032232_create_seat_locks_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_base', function (Blueprint $table) {
            $table->uuid('id')->primary();      // id uuid [pk]
            $table->string('name');             // tên: "Base Ticket"
            $table->decimal('base_price', 10, 2); // base_price decimal(10,2)
            $table->boolean('is_active')->default(true);
            $table->timestamps();               // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_base');
    }
};
