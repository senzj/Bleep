<?php

namespace App\Models;

use App\Traits\HasAnonymousName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

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
        'is_anonymous'      => 'boolean',
        'is_nsfw'           => 'boolean',
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
     * Helpers
     */

    // Helper to get view count (with caching)
    public function viewCount(): int
    {
        return $this->views ?? $this->views()->count();
    }

    // Helper to record a view
    public function recordView(?User $user = null, ?string $sessionId = null): void
    {
        $created = BleepViews::firstOrCreate([
            'bleep_id'  => $this->id,
            'user_id'   => $user?->id,
            'session_id'=> $user ? null : $sessionId,
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
        if (!$user) return false;

        // Use loaded relation if available to avoid extra query
        if ($this->relationLoaded('likes')) {
            return $this->likes->where('user_id', $user->id)->isNotEmpty();
        }

        return $this->likes()->where('user_id', $user->id)->exists();
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
