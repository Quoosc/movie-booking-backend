<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Promotion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'promotions';

    // PK đang là promotion_id (theo migration bạn gửi)
    protected $primaryKey = 'promotion_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'usage_limit',
        'per_user_limit',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'start_date'     => 'datetime',
        'end_date'       => 'datetime',
        'usage_limit'    => 'integer',
        'per_user_limit' => 'integer',
        'is_active'      => 'boolean',
    ];

    // Nếu sau này có BookingPromotion model thì thêm quan hệ:
    // public function bookingPromotions()
    // {
    //     return $this->hasMany(BookingPromotion::class, 'promotion_id', 'promotion_id');
    // }

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
