<?php
// app/Models/RefreshToken.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RefreshToken extends Model
{
    use HasFactory;

    protected $table = 'refresh_tokens';
    protected $primaryKey = 'token_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // bảng của mình không có updated_at

    protected $fillable = [
        'token_id',
        'user_id',
        'token',
        'revoked_at',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (RefreshToken $model) {
            if (!$model->token_id) {
                $model->token_id = (string) Str::uuid();
            }

            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }
}
