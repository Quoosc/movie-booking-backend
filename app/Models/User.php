<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    public $incrementing = false;          // UUID, không auto increment
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'username',
        'email',
        'phoneNumber',
        'password',
        'provider',
        'role',
        'avatar_url',
        'avatar_cloudinary_id',
        'loyalty_points',
        'membership_tier_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'loyalty_points' => 'integer',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function membershipTier()
    {
        return $this->belongsTo(MembershipTier::class, 'membership_tier_id', 'tier_id');
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class, 'user_id', 'user_id');
    }
}
