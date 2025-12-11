<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Booking extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bookings';

    protected $fillable = [
        'user_id',
        'showtime_id',
        'status',
        'total_price',
        'discount_reason',
        'discount_value',
        'final_price',
        'booked_at',
        'payment_expires_at',
        'qr_payload',
        'qr_code',
        'loyalty_points_awarded',
        'refunded',
        'refunded_at',
        'refund_reason',
    ];

    protected $casts = [
        'total_price'            => 'decimal:2',
        'discount_value'         => 'decimal:2',
        'final_price'            => 'decimal:2',
        'booked_at'              => 'datetime',
        'payment_expires_at'     => 'datetime',
        'refunded'               => 'boolean',
        'refunded_at'            => 'datetime',
        'loyalty_points_awarded' => 'boolean',
        'status'                 => BookingStatus::class,
    ];

    // ====== relationships ======

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function showtime()
    {
        return $this->belongsTo(Showtime::class);
    }

    public function bookingSeats()
    {
        return $this->hasMany(BookingSeat::class);
    }

    public function bookingSnacks()
    {
        return $this->hasMany(BookingSnack::class);
    }

    public function bookingPromotions()
    {
        return $this->hasMany(BookingPromotion::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
