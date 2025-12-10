<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commentslikes extends Model
{
    protected $table = 'comments_likes';

    protected $fillable = [
        'user_id',
        'comments_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comments::class, 'comments_id');
    }
}
