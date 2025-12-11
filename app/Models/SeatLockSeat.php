<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SeatLockSeat extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'seat_lock_seats';
    protected $primaryKey = 'id';

    protected $fillable = [
        'seat_lock_id',
        'showtime_seat_id',
        'ticket_type_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    // ===== RELATIONSHIPS =====

    public function seatLock()
    {
        return $this->belongsTo(SeatLock::class, 'seat_lock_id', 'seat_lock_id');
    }

    public function showtimeSeat()
    {
        return $this->belongsTo(ShowtimeSeat::class, 'showtime_seat_id', 'showtime_seat_id');
    }

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id', 'id');
    }
}
