<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BookingSnack extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'booking_snacks';
    protected $primaryKey = 'booking_snack_id'; // nếu migration của bạn dùng tên khác thì sửa lại
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'booking_id',
        'snack_id',
        'quantity',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function snack()
    {
        return $this->belongsTo(Snack::class, 'snack_id', 'snack_id');
    }
}
