<?php
// app/Models/User.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
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
        'remember_token',
    ];

    protected $casts = [
        'loyalty_points' => 'integer',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (User $user) {
            if (!$user->user_id) {
                $user->user_id = (string) Str::uuid();
            }
        });
    }

    // ====== Relationships ======

    public function membershipTier()
    {
        return $this->belongsTo(MembershipTier::class, 'membership_tier_id', 'tier_id');
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class, 'user_id', 'user_id');
    }
}
