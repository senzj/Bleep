<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'remember_token',
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
        if (!$user->getKey()) {
            return false;
        }

        return $this->following()->where('users.id', $user->id)->exists();
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
}
