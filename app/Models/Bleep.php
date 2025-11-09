<?php

namespace App\Models;

use App\Traits\HasAnonymousName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bleep extends Model
{
    use HasAnonymousName, SoftDeletes;

    protected $fillable = [
        'message',
        'is_anonymous',
        'deleted_by_author',
    ];

    protected $casts = [
        'deleted_by_author' => 'boolean',
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
     * Relation to Comments model
     */
    public function comments()
    {
        return $this->hasMany(Comments::class);
    }

    /**
     * Relation to Shares model
     */
    public function shares()
    {
        return $this->hasMany(Share::class);
    }

    /**
     * Relation to Reposts model
     */
    public function reposts()
    {
        return $this->hasMany(Repost::class);
    }

    /**
     * Check if user liked this bleep
     */
    public function isLikedBy($user)
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * Boot method to handle cascading deletes
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($bleep) {
            // Delete related records when bleep is deleted
            // Note: Shares are NOT deleted so tokens remain valid
            $bleep->comments()->delete();
            $bleep->likes()->delete();
            $bleep->reposts()->delete();
        });
    }
}
