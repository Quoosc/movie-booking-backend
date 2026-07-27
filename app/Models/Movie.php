<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Movie extends Model
{
    use HasFactory;

    protected $table = 'movies';

    // PK là movie_id (UUID)
    protected $primaryKey = 'movie_id';
    public $incrementing = false;
    protected $keyType = 'string';

    // Không cần fillable movie_id vì sẽ tự generate
    protected $fillable = [
        'title',
        'genre',
        'description',
        'duration',
        'minimum_age',
        'director',
        'actors',
        'poster_url',
        'poster_cloudinary_id',
        'trailer_url',
        'status',
        'language',
        'premiere_date',
    ];

    protected $casts = [
        'premiere_date' => 'date:Y-m-d',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($movie) {
            if (empty($movie->movie_id)) {
                $movie->movie_id = (string) Str::uuid();
            }
        });
    }


    public function showtimes()
    {
        return $this->hasMany(Showtime::class, 'movie_id', 'movie_id');
    }

    public function reviews()
    {
        return $this->hasMany(\App\Models\MovieReview::class, 'movie_id', 'movie_id')
            ->whereNull('parent_id')
            ->where('status', 'PUBLISHED');
    }
}
