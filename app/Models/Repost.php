<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Collection\Collection;

class Repost extends Model
{
    protected $fillable = [
        'bleep_id',
        'user_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relationships to user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relationships to bleep
    public function bleep(): BelongsTo
    {
        return $this->belongsTo(Bleep::class);
    }

    /**
     * Get reposts visible to a given user (only from followed users)
     */
    public static function visibleToUser($userId, $bleepId): \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
    {
        if (!$userId) {
            return collect();
        }

        // Get IDs of users that current user follows
        $followedIds = Following::where('follower_id', $userId)
            ->pluck('followed_id')
            ->toArray();

        // Return reposts from followed users only
        return static::where('bleep_id', $bleepId)
            ->whereIn('user_id', $followedIds)
            ->with('user')
            ->latest()
            ->get();
    }
}
