<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    use HasFactory;

    protected $table = 'refresh_tokens';
    protected $primaryKey = 'token_id';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = true; // nếu trong migration dùng timestamps()

    protected $fillable = [
        'token_id',
        'user_id',
        'token',
        'revoked_at',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
