<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Cinema extends Model
{
    use HasFactory;

    protected $table = 'cinemas';
    protected $primaryKey = 'cinema_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'address',
        'hotline',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if ($model->is_active === null) {
                $model->is_active = true;
            }
        });
    }

    public function rooms()
    {
        return $this->hasMany(Room::class, 'cinema_id', 'cinema_id');
    }
}
