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
    protected $primaryKey = 'payment_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'booking_id',
        'user_id',
        'amount',
        'currency',
        'gateway_amount',
        'gateway_currency',
        'exchange_rate',
        'status',
        'method',
        'order_id',
        'txn_ref',
        'payment_url',
        'gateway_response',
        'paid_at',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'gateway_amount' => 'decimal:2',
        'exchange_rate'  => 'decimal:4',
        'gateway_response' => 'array',
        'paid_at'        => 'datetime',
        'status'         => PaymentStatus::class,
        'method'         => PaymentMethod::class,
    ];

    // ====== relationships ======

    // Payment.php

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'payment_id', 'payment_id');
    }


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
