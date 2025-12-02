<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Comment;

class Commentslikes extends Model
{
    protected $table = 'comments_likes';

    protected $fillable = [
        'user_id',
        'comments_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comment()
    {
        return $this->belongsTo(Comments::class, 'comments_id');
    }
}
