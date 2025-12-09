<?php

// app/Models/MembershipTier.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MembershipTier extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'membership_tiers';
    protected $primaryKey = 'membership_tier_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'min_points',
        'discount_type',   // PERCENTAGE | FIXED_AMOUNT
        'discount_value',
        'description',
        'is_active',
    ];

    protected $casts = [
        'min_points'     => 'integer',
        'discount_value' => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'membership_tier_id', 'membership_tier_id');
    }
}
