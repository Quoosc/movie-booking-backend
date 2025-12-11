<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeatLock extends Model
{
    use HasFactory;

    protected $table = 'seat_locks';
    protected $primaryKey = 'seat_lock_id';   // PK dạng UUID
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'showtime_id',
        'lock_owner_id',      // UUID user hoặc sessionId
        'lock_owner_type',    // USER / GUEST_SESSION
        'lock_key',           // chuỗi random dùng để release
        'expires_at',
        'status',             // nếu trong DB có cột status
    ];

    protected $casts = [
        'expires_at' => 'datetime',
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

    // ===== RELATIONSHIPS =====

    public function showtime()
    {
        return $this->belongsTo(Showtime::class, 'showtime_id', 'showtime_id');
    }

    public function lockSeats()
    {
        // bảng seat_lock_seats, PK là seat_lock_seat_id, FK seat_lock_id
        return $this->hasMany(SeatLockSeat::class, 'seat_lock_id', 'seat_lock_id');
    }
}
