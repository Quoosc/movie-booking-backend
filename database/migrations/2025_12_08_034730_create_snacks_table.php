<?php
// database/migrations/2025_12_08_032853_create_price_modifiers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snacks', function (Blueprint $table) {
            $table->uuid('snack_id')->primary();

            $table->uuid('cinema_id');
            $table->string('name');
            $table->string('type', 50); // popcorn, drink, combo

            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);

            $table->string('image_url')->nullable();
            $table->string('image_cloudinary_id')->nullable();

            // thêm timestamps để tiện track, spec không cấm
            $table->timestamps();

            $table->foreign('cinema_id')
                  ->references('cinema_id')
                  ->on('cinemas')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snacks');
    }
};

