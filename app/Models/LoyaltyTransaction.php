<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LoyaltyTransaction extends Model
{
    protected $table = 'loyalty_transactions';
    protected $primaryKey = 'loyalty_transaction_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'type',
        'points',
        'balance_after',
        'source_type',
        'source_id',
        'description',
        'expires_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LoyaltyTransaction $transaction) {
            $transaction->loyalty_transaction_id ??= (string) Str::uuid();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
