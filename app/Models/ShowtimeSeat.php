<?php

namespace App\Models;

use App\Enums\SeatStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ShowtimeSeat extends Model
{
    use HasFactory;

    protected $table = 'showtime_seats';
    protected $primaryKey = 'showtime_seat_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'showtime_id',
        'seat_id',
        'seat_status',
        'price',
        'price_breakdown',
    ];

    protected $casts = [
        'seat_status' => SeatStatus::class,
    ];

    // Backward compat: status <-> seat_status
    public function getStatusAttribute()
    {
        return $this->seat_status;
    }

    public function setStatusAttribute($value)
    {
        $this->attributes['seat_status'] = $value;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function showtime()
    {
        return $this->belongsTo(Showtime::class, 'showtime_id', 'showtime_id');
    }

    public function seat()
    {
        return $this->belongsTo(Seat::class, 'seat_id', 'seat_id');
    }
}
