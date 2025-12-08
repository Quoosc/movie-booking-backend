<?php

// app/Models/Showtime.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Showtime extends Model
{
    use HasFactory;

    protected $table = 'showtimes';

    protected $fillable = [
        'movie_id',
        'room_id',
        'format',
        'start_time',
    ];

    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }
}

