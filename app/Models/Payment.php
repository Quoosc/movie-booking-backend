<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'payments';

    protected $fillable = [
        'booking_id',
        'amount',
        'currency',
        'method',
        'status',
        'gateway_amount',
        'gateway_currency',
        'exchange_rate',
        'transaction_id',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'amount'         => 'float',
        'gateway_amount' => 'float',
        'exchange_rate'  => 'float',
        'completed_at'   => 'datetime',
        'status'         => PaymentStatus::class,
        'method'         => PaymentMethod::class,
    ];

    // ====== relationships ======

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function refund()
    {
        return $this->hasOne(Refund::class);
    }
}
