<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->char('movie_id', 36)->primary();           // UUID
            $table->string('title');                            // title
            $table->string('genre')->nullable();                // genre
            $table->text('description')->nullable();            // description
            $table->integer('duration');                        // duration (phút)
            $table->integer('minimum_age')->nullable();         // minimum_age
            $table->string('director')->nullable();             // director
            $table->text('actors')->nullable();                 // actors
            $table->string('poster_url')->nullable();           // poster_url
            $table->string('poster_cloudinary_id')->nullable(); // poster_cloudinary_id
            $table->string('trailer_url')->nullable();          // trailer_url
            $table->enum('status', ['SHOWING', 'UPCOMING'])->default('UPCOMING'); // MovieStatus
            $table->string('language')->nullable();             // language
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
