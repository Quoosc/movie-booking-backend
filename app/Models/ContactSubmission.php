<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContactSubmission extends Model
{
    use HasFactory;

    protected $table = 'contact_submissions';
    protected $primaryKey = 'contact_submission_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'message',
        'source_page',
        'ip_address',
        'user_agent',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
