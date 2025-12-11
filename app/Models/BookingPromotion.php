<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BookingPromotion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'booking_promotions';

    protected $fillable = [
        'booking_id',
        'promotion_id',
        'discount_value',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class, 'promotion_id', 'promotion_id');
    }
}
