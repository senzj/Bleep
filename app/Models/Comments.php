<?php

namespace App\Models;

use App\Models\User;
use App\Models\Commentslikes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAnonymousName;

class Comments extends Model
{
    use HasAnonymousName, SoftDeletes;

    protected $table = 'comments';

    protected $fillable = [
        'user_id',
        'bleep_id',
        'parent_id',
        'message',
        'media_path',
        'is_anonymous',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** User who posted the comment */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Bleep this comment belongs to */
    public function bleep()
    {
        return $this->belongsTo(Bleep::class);
    }

    /** Parent comment (for replies) */
    public function parent()
    {
        return $this->belongsTo(Comments::class, 'parent_id');
    }

    /** Replies to this comment */
    public function replies()
    {
        return $this->hasMany(Comments::class, 'parent_id')
            ->with(['user', 'likes'])
            ->latest();
    }

    /** Likes for this comment */
    public function likes()
    {
        return $this->hasMany(Commentslikes::class, 'comments_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function likesCount(): int
    {
        return (int) $this->likes()->count();
    }

    public function isLikedBy(User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function depth(): int
    {
        return $this->parent ? $this->parent->depth() + 1 : 0;
    }
}
