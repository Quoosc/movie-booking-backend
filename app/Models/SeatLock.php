<?php

namespace App\Models;

use App\Enums\LockOwnerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SeatLock extends Model
{
    use HasFactory;

    protected $table = 'seat_locks';
    protected $primaryKey = 'seat_lock_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // only created_at, expires_at

    protected $fillable = [
        'lock_key',
        'lock_owner_id',
        'lock_owner_type',
        'user_id',
        'showtime_id',
        'expires_at',
    ];

    protected $casts = [
        'lock_owner_type' => LockOwnerType::class,
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = Carbon::now();
            }
        });
    }

    // ===== RELATIONSHIPS =====

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function showtime()
    {
        return $this->belongsTo(Showtime::class, 'showtime_id', 'showtime_id');
    }

    public function seatLockSeats()
    {
        return $this->hasMany(SeatLockSeat::class, 'seat_lock_id', 'seat_lock_id');
    }

    // ===== METHODS =====

    public function isActive(): bool
    {
        return Carbon::now()->isBefore($this->expires_at);
    }

    public function getRemainingSeconds(): int
    {
        $diff = Carbon::now()->diffInSeconds($this->expires_at, false);
        return max(0, (int) $diff);
    }
}
