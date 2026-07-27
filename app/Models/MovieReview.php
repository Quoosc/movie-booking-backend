<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MovieReview extends Model
{
    protected $table = 'movie_reviews';
    protected $primaryKey = 'review_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'movie_id',
        'parent_id',
        'user_id',
        'reviewer_name',
        'rating',
        'comment',
        'is_spoiler',
        'status',
        'edited_at',
        'image_url',
        'video_url',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_spoiler' => 'boolean',
        'edited_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->review_id)) {
                $model->review_id = (string) Str::uuid();
            }
        });
    }

    public function movie()
    {
        return $this->belongsTo(Movie::class, 'movie_id', 'movie_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function reactions()
    {
        return $this->hasMany(ReviewReaction::class, 'review_id', 'review_id');
    }

    public function replies()
    {
        return $this->hasMany(MovieReview::class, 'parent_id', 'review_id');
    }

    public function reports()
    {
        return $this->hasMany(ReviewReport::class, 'review_id', 'review_id');
    }

    public function moderationLogs()
    {
        return $this->hasMany(ReviewModerationLog::class, 'review_id', 'review_id');
    }

    public function latestModerationLog()
    {
        return $this->hasOne(ReviewModerationLog::class, 'review_id', 'review_id')
            ->latestOfMany('created_at');
    }
}
