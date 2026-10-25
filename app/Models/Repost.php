<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Collection\Collection;

class Repost extends Model
{
    protected $fillable = [
        'bleep_id',
        'user_id',
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
     * Get reposts visible to a given user (only from followed users).
     */
    public static function visibleToUser($userId, $bleepId): \Illuminate\Support\Collection
    {
        if (! $userId) {
            return collect();
        }

        return static::visibleToUserGrouped((int) $userId, [$bleepId])
            ->get((int) $bleepId, collect());
    }

    /**
     * Get followed-user reposts for many bleeps in two queries, grouped by bleep id.
     *
     * @param  iterable<int|string>  $bleepIds
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Collection<int, self>>
     */
    public static function visibleToUserGrouped(?int $userId, iterable $bleepIds): \Illuminate\Support\Collection
    {
        if (! $userId) {
            return collect();
        }

        $bleepIds = collect($bleepIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($bleepIds->isEmpty()) {
            return collect();
        }

        $followedIds = Following::where('follower_id', $userId)
            ->pluck('followed_id');

        if ($followedIds->isEmpty()) {
            return collect();
        }

        return static::query()
            ->whereIn('bleep_id', $bleepIds)
            ->whereIn('user_id', $followedIds)
            ->with('user')
            ->latest()
            ->get()
            ->groupBy(fn (Repost $repost) => (int) $repost->bleep_id);
    }

    /**
     * Attach followed-user reposts to a collection of bleeps using batched queries.
     *
     * @param  iterable<Bleep>  $bleeps
     */
    public static function attachVisibleTo(iterable $bleeps, ?int $userId): void
    {
        $bleeps = collect($bleeps);

        if ($bleeps->isEmpty()) {
            return;
        }

        $grouped = static::visibleToUserGrouped($userId, $bleeps->pluck('id'));

        foreach ($bleeps as $bleep) {
            if (! $bleep instanceof Bleep) {
                continue;
            }

            $bleep->followedReposts = $grouped->get((int) $bleep->id, collect());
        }
    }
}
