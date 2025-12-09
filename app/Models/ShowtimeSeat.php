<?php

namespace App\Models;

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
        'showtime_seat_id',
        'showtime_id',
        'seat_id',
        'seat_status',
        'price',
        'price_breakdown',
    ];

    protected $casts = [
        'price' => 'decimal:2',
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
}
