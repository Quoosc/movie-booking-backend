<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Showtime extends Model
{
    use HasFactory;

    protected $table = 'showtimes';
    protected $primaryKey = 'showtime_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'movie_id',
        'room_id',
        'format',
        'start_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function movie()
    {
        return $this->belongsTo(Movie::class, 'movie_id', 'movie_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }

    // Mấy cái dưới chủ yếu để delete showtime check, chưa xài có thể comment tạm

    // public function showtimeSeats()
    // {
    //     return $this->hasMany(ShowtimeSeat::class, 'showtime_id', 'showtime_id');
    // }

    // public function seatLocks()
    // {
    //     return $this->hasMany(SeatLock::class, 'showtime_id', 'showtime_id');
    // }

    // public function bookings()
    // {
    //     return $this->hasMany(Booking::class, 'showtime_id', 'showtime_id');
    // }
}
