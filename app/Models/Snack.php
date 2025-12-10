<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Snack extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'snacks';
    protected $primaryKey = 'snack_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'cinema_id',
        'name',
        'description',
        'price',
        'type',
        'image_url',
        'image_cloudinary_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function cinema()
    {
        return $this->belongsTo(Cinema::class, 'cinema_id', 'cinema_id');
    }

    public function bookingSnacks()
    {
        return $this->hasMany(BookingSnack::class, 'snack_id', 'snack_id');
    }
}
