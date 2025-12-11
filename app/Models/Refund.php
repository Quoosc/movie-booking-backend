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
        'amount',
        'refund_method',
        'reason',
        'refund_gateway_txn_id',
        'refunded_at',
    ];

    protected $casts = [
        'amount'      => 'float',
        'refunded_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
