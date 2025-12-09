<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PriceModifier extends Model
{
    use HasFactory;

    protected $table = 'price_modifiers';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'condition_type',   // DAY_TYPE, TIME_RANGE, FORMAT, ROOM_TYPE, SEAT_TYPE, TICKET_TYPE
        'condition_value',  // WEEKEND, MORNING, 2D, VIP,...
        'modifier_type',    // PERCENTAGE, FIXED_AMOUNT
        'modifier_value',
        'is_active',
    ];

    protected $casts = [
        'modifier_value' => 'decimal:2',
        'is_active'      => 'boolean',
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
