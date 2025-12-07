<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showtime_ticket_types', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('showtime_id');
            $table->uuid('ticket_type_id');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('showtime_id')
                  ->references('showtime_id')
                  ->on('showtimes')
                  ->onDelete('cascade');

            $table->foreign('ticket_type_id')
                  ->references('id')
                  ->on('ticket_types')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtime_ticket_types');
    }
};
