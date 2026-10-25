<?php

namespace App\Models;

use App\Traits\HasAnonymousName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Bleep extends Model
{
    use HasAnonymousName, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'message',
        'is_anonymous',
        'is_nsfw',
        'views',
        'deleted_by_author',
        'media_path',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_nsfw' => 'boolean',
        'deleted_by_author' => 'boolean',
        'views' => 'integer',
    ];

    /**
     * Relationships
     */

    // Relation to User model
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relation to Likes model
    public function likes(): HasMany
    {
        return $this->hasMany(Likes::class);
    }

    // Relation to Comments model
    public function comments(): HasMany
    {
        return $this->hasMany(Comments::class);
    }

    // Relation to Shares model
    public function shares(): HasMany
    {
        return $this->hasMany(Share::class);
    }

    // Relation to Reposts model
    public function reposts(): HasMany
    {
        return $this->hasMany(Repost::class);
    }

    // Media relation
    public function media(): HasMany
    {
        return $this->hasMany(BleepMedia::class);
    }

    // Views relation
    public function views(): HasMany
    {
        return $this->hasMany(BleepViews::class);
    }

    // reports relation
    public function reports()
    {
        return $this->morphMany(Reports::class, 'reportable');
    }

    /**
     * Eager-load everything a bleep card needs to render without extra queries.
     *
     * Adds likes_count, comments_count, shares_count and reposts_count aggregates,
     * plus the viewer-specific liked_by_viewer / has_reposted flags.
     */
    public function scopeWithCardStats(Builder $query, ?User $viewer = null): Builder
    {
        $query->with(['user.preferences', 'media'])
            ->withCount(['likes', 'comments', 'shares', 'reposts']);

        if ($viewer) {
            $query->withExists([
                'likes as liked_by_viewer' => fn (Builder $likes) => $likes->where('user_id', $viewer->id),
                'reposts as has_reposted' => fn (Builder $reposts) => $reposts->where('user_id', $viewer->id),
            ]);
        }

        return $query;
    }

    /**
     * Helpers
     */

    // Helper to get view count (with caching)
    public function viewCount(): int
    {
        return $this->views ?? $this->views()->count();
    }

    public function likeCount(): int
    {
        return (int) ($this->attributes['likes_count'] ?? $this->likes()->count());
    }

    public function commentCount(): int
    {
        return (int) ($this->attributes['comments_count'] ?? $this->comments()->count());
    }

    public function shareCount(): int
    {
        return (int) ($this->attributes['shares_count'] ?? $this->shares()->count());
    }

    public function repostCount(): int
    {
        return (int) ($this->attributes['reposts_count'] ?? $this->reposts()->count());
    }

    /**
     * Whether the given viewer has reposted this bleep.
     */
    public function hasRepostedByViewer(?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if (array_key_exists('has_reposted', $this->attributes)) {
            return (bool) $this->attributes['has_reposted'];
        }

        return $this->reposts()->where('user_id', $viewer->id)->exists();
    }

    // Helper to record a view
    public function recordView(?User $user = null, ?string $sessionId = null): void
    {
        $created = BleepViews::firstOrCreate([
            'bleep_id' => $this->id,
            'user_id' => $user?->id,
            'session_id' => $user ? null : $sessionId,
        ], [
            'viewed_at' => now(),
        ]);

        if ($created->wasRecentlyCreated) {
            static::withoutTimestamps(function () {
                $this->newQuery()->whereKey($this->id)->increment('views');
            });

            $this->refresh(['views']);
        }
    }

    // Helper to check if a user liked this bleep
    public function isLikedBy($user): bool
    {
        if (! $user) {
            return false;
        }

        // Use the pre-computed exists flag when the card query supplied it.
        if (array_key_exists('liked_by_viewer', $this->attributes)) {
            return (bool) $this->attributes['liked_by_viewer'];
        }

        // Use loaded relation if available to avoid extra query
        if ($this->relationLoaded('likes')) {
            return $this->likes->where('user_id', $user->id)->isNotEmpty();
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether a viewer may see this bleep.
     *
     * Guests may see public, non-deleted bleeps. Private-profile posts require
     * an accepted follow relationship. Soft-deleted posts are limited to the
     * author and administrators.
     */
    public function isVisibleTo(?User $viewer = null): bool
    {
        $author = $this->user;

        if (! $author) {
            return ! $this->trashed();
        }

        if ($this->trashed()) {
            return $viewer !== null
                && ((int) $viewer->id === (int) $this->user_id || $viewer->hasAdminAccess());
        }

        if ($viewer && $viewer->isBlockedOrHasBlocked($author)) {
            return false;
        }

        if ($viewer && (int) $viewer->id === (int) $this->user_id) {
            return true;
        }

        $isPrivate = (bool) ($author->preferences?->private_profile ?? false);

        return ! $isPrivate || ($viewer !== null && $viewer->isFollowing($author));
    }

    // Cleanup media files when bleep is deleted (soft delete)
    protected static function booted()
    {
        static::deleting(function (Bleep $bleep) {
            // Delete media files + rows (soft delete still triggers this)
            foreach ($bleep->media as $m) {
                Storage::disk('public')->delete($m->path);
                $m->delete(); // hard delete (BleepMedia has no SoftDeletes)
            }

            // Remove legacy single media file if present
            if ($bleep->media_path) {
                Storage::disk('public')->delete($bleep->media_path);
            }

            // Other related cleanup
            $bleep->comments()->delete();
            $bleep->likes()->delete();
            $bleep->reposts()->delete();

            // Nullify shares so tokens remain but detach
            $bleep->shares()->update(['bleep_id' => null, 'user_id' => null]);
        });
    }
}
