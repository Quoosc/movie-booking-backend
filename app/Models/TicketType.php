<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketType extends Model
{
    use HasFactory;

    protected $table = 'ticket_types';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'label',
        'modifier_type',
        'modifier_value',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'modifier_value' => 'float',
        'active' => 'boolean',
        'sort_order' => 'integer',
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

    public function showtimeTicketTypes()
    {
        return $this->hasMany(ShowtimeTicketType::class, 'ticket_type_id', 'id');
    }
}
