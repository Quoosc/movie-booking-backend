<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewReaction extends Model
{
    protected $table = 'review_reactions';
    protected $fillable = ['review_id', 'reaction_type', 'user_id', 'ip_address'];

    public function review()
    {
        return $this->belongsTo(MovieReview::class, 'review_id', 'review_id');
    }
}
