<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReviewModerationLog extends Model
{
    protected $table = 'review_moderation_logs';
    protected $primaryKey = 'moderation_log_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'review_id',
        'moderator_id',
        'action',
        'previous_status',
        'next_status',
        'reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReviewModerationLog $log) {
            $log->moderation_log_id ??= (string) Str::uuid();
        });
    }

    public function review()
    {
        return $this->belongsTo(MovieReview::class, 'review_id', 'review_id');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id', 'user_id');
    }
}
