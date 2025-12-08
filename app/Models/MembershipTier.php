<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipTier extends Model
{
    use HasFactory;

    protected $table = 'membership_tiers';
    protected $primaryKey = 'tier_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tier_id',
        'name',
        'min_points',
        'discount_type',
        'discount_value',
        'description',
        'is_active',
    ];

    protected $casts = [
        'min_points'    => 'integer',
        'discount_value'=> 'float',
        'is_active'     => 'boolean',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'membership_tier_id', 'tier_id');
    }
}
