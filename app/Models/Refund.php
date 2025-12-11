<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Refund extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'refunds';

    protected $fillable = [
        'payment_id',
        'booking_id',
        'user_id',
        'amount',
        'currency',
        'reason',
        'status',
        'gateway_refund_id',
        'gateway_response',
        'refunded_at',
    ];

    protected $casts = [
        'status' => \App\Enums\RefundStatus::class,
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'refunded_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
