<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'dname',
        'username',
        'bio',
        'email',
        'password',
        'role',
        'timezone',
        'is_verified',
        'is_banned',
        'banned_until',
        'ban_reason',
        'profile_picture',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'is_banned' => 'boolean',
            'banned_until' => 'datetime',
        ];
    }

    /**
     * Relation to Bleep model
     */
    public function bleeps(): HasMany
    {
        return $this->hasMany(Bleep::class);
    }

    /**
     * Get users that this user follows
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'followings', 'follower_id', 'followed_id')
            ->withTimestamps();
    }

    /**
     * Get users that follow this user
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'followings', 'followed_id', 'follower_id')
            ->withTimestamps();
    }

    /**
     * Check if user follows another user
     */
    public function isFollowing(User $user): bool
    {
        return $this->following()->where('followed_id', $user->id)->exists();
    }

    // safe computed accessor — does not change DB value
    public function getProfilePictureUrlAttribute(): ?string
    {
        $path = $this->getOriginal('profile_picture'); // raw DB value
        if (! $path) {
            return asset('images/avatar/default.jpg');
        }

        $path = ltrim($path, '/');
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return asset('storage/' . $path);
        }
        return asset('images/avatar/default.jpg');
    }

    // checks if user is admin/moderator
    public function hasAdminAccess(): bool
    {
        return in_array($this->role, ['admin', 'moderator']);
    }

    /**
     * Check if user is currently banned
     */
    public function isBanned(): bool
    {
        if (!$this->is_banned) {
            return false;
        }

        // Auto-unban if temporary ban expired
        if ($this->banned_until && now()->isAfter($this->banned_until)) {
            $this->update([
                'is_banned' => false,
                'banned_until' => null,
                'ban_reason' => null,
            ]);
            return false;
        }

        return true;
    }

    public function rememberedDevices(): HasMany
    {
        return $this->hasMany(RememberedDevice::class);
    }

    /**
     * User preferences relationship
     */
    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreferences::class);
    }

    /**
     * Get user preferences or create with defaults
     */
    public function getPreferences(): UserPreferences
    {
        return $this->preferences ?? $this->preferences()->create(UserPreferences::defaults());
    }

    /**
     * Get nav layout preference (horizontal or vertical)
     */
    public function getNavLayout(): string
    {
        return $this->preferences?->nav_layout ?? 'horizontal';
    }

    /**
     * Limit remembered devices to 5 per user.
     * Deletes oldest if limit exceeded.
     */
    public function pruneRememberedDevices(): void
    {
        $count = $this->rememberedDevices()->count();
        if ($count > 5) {
            $this->rememberedDevices()
                ->orderByRaw('COALESCE(last_used_at, created_at) ASC')
                ->limit($count - 5)
                ->delete();
        }
    }

    /**
     * Check if this user is friend with another user (both follow each other)
     */
    public function isFriend(User $user): bool
    {
        // I follow them
        $iFollowThem = $this->following()->where('followed_id', $user->id)->exists();
        // They follow me
        $theyFollowMe = $user->following()->where('followed_id', $this->id)->exists();

        return $iFollowThem && $theyFollowMe;
    }

    /**
     * Check if this users are mutuals (Friend's friend)
     */
    public function isMutual(User $user): bool
    {
        return $this->following()->whereHas('following', function ($query) use ($user) {
            $query->where('following_id', $user->id);
        })->exists() && $user->following()->whereHas('following', function ($query) use ($user) {
            $query->where('following_id', $this->id);
        })->exists();
    }

    /**
     * Follow request relationships and checks from user who receives the request
     */
    public function followRequests(): HasMany
    {
        return $this->hasMany(FollowRequest::class, 'target_id');
    }

    /**
     * Follow request relationships and checks from user who sent the request
     */
    public function sentFollowRequests(): HasMany
    {
        return $this->hasMany(FollowRequest::class, 'requester_id');
    }

    /**
     * Check if this user has a pending follow request from another user
     */
    public function hasPendingRequestFrom(User $user): bool
    {
        return $this->followRequests()
            ->where('requester_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Check if this user has a pending follow request to another user
     */
    public function hasSentRequestTo(User $user): bool
    {
        return $this->sentFollowRequests()
            ->where('target_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }
}
