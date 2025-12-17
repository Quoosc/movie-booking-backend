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
    protected $primaryKey = 'showtime_seat_id';   // ✅ PK đúng
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'showtime_id',
        'seat_id',
        'row_label',
        'seat_number',
        'seat_type',
        'seat_status',  // Database column is 'seat_status'
        'price',
        'price_breakdown',
    ];

    protected $casts = [
        'seat_status' => SeatStatus::class,
    ];

    // Accessor to map 'status' to 'seat_status' for backwards compatibility
    public function getStatusAttribute()
    {
        return $this->seat_status;
    }

    // Mutator to map 'status' to 'seat_status'
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
