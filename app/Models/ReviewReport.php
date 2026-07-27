<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReviewReport extends Model
{
    protected $table = 'review_reports';
    protected $primaryKey = 'report_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'review_id',
        'user_id',
        'ip_address',
        'reason',
        'details',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReviewReport $report) {
            $report->report_id ??= (string) Str::uuid();
        });
    }

    public function review()
    {
        return $this->belongsTo(MovieReview::class, 'review_id', 'review_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
