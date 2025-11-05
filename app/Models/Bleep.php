<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bleep extends Model
{
    protected $fillable = [
        'message',
    ];

    /**
     * Relation to User model
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation to Likes model
     */
    public function likes()
    {
        return $this->hasMany(Likes::class);
    }

    /**
     * Check if user liked this bleep
     */
    public function isLikedBy($user)
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }
}
