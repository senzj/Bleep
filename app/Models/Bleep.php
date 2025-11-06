<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bleep extends Model
{
    protected $fillable = [
        'message',
        'is_anonymous',
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
     * Check if user liked this bleep
     */
    public function isLikedBy($user)
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * Deterministic anonymous display name for this bleep (viewer-specific).
     * Uses bleep id + viewer seed so different viewers get different names,
     * but the same viewer sees the same name for the same bleep.
     */
    public function anonymousDisplayNameFor(string|int $viewerSeed): string
    {
        $firstParts = [
            'Rampage','Clam','Sunny','Brave','Sneaky','Mighty','Quiet','Spicy','Fuzzy','Neon',
            'Turbo','Happy','Icy','Rusty','Velvet','Silver','Crimson','Jolly','Gloomy','Zen'
        ];

        $secondParts = [
            'Berry','Banana','Fox','Tiger','Pancake','Nimbus','Penguin','Pixel','Breeze','Blossom',
            'Rocket','Dandelion','Echo','Shadow','Nova','Sailor','Comet','Mango','Quartz','Marsh'
        ];

        // deterministic hash from bleep id + viewer seed
        $hash = md5((string) $this->id . '|' . (string) $viewerSeed);

        $firstIndex  = hexdec(substr($hash, 0, 8)) % count($firstParts);
        $secondIndex = hexdec(substr($hash, 8, 8)) % count($secondParts);

        return ucwords($firstParts[$firstIndex] . ' ' . $secondParts[$secondIndex]);
    }

}
