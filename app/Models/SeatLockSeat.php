<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeatLockSeat extends Model
{
    use HasFactory;

    protected $table = 'seat_lock_seats';

    // Nếu migration dùng cột 'id' thì để như dưới,
    // còn nếu là 'seat_lock_seat_id' thì đổi lại cho khớp.
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'seat_lock_id',
        'showtime_seat_id',
        'ticket_type_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
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

    /* ================= RELATIONSHIPS ================= */

    // khóa cha (SeatLock)
    public function seatLock()
    {
        return $this->belongsTo(SeatLock::class, 'seat_lock_id', 'seat_lock_id');
    }

    // ghế cụ thể trong suất chiếu
    public function showtimeSeat()
    {
        return $this->belongsTo(ShowtimeSeat::class, 'showtime_seat_id', 'showtime_seat_id');
    }

    // loại vé được chọn cho ghế này
    public function ticketType()
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id', 'ticket_type_id');
    }
}
