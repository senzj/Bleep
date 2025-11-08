<?php

namespace App\Traits;

trait HasAnonymousName
{
    /**
     * Deterministic anonymous display name (viewer-specific).
     * Uses user_id + viewer seed so different viewers get different names,
     * but the same viewer sees the same name for the same user across all their content.
     */
    public function anonymousDisplayNameFor(string|int $viewerSeed): string
    {
        $firstParts = [
            'Rampage','Clam','Sunny','Brave','Sneaky','Mighty','Quiet','Spicy','Fuzzy','Neon','Apple','Cotton',
            'Turbo','Happy','Icy','Rusty','Velvet','Silver','Crimson','Jolly','Gloomy','Zen','Cherry','Marsh',
            'Clever','Blissful','Frosty','Witty','Cosmic','Lucky','Stormy','Mellow','Amber','Dizzy',
            'Chilly','Dreamy','Zesty','Golden','Peachy','Twisty','Snazzy','Shadowy','Electric','Plush', 'Fluffy',
            'Dusty','Tiny','Rowdy','Snappy','Bubbly','Sleepy','Wild','Royal','Cheerful','Mystic', 'Joyful'
        ];

        $secondParts = [
            'Berry','Banana','Fox','Tiger','Pancake','Nimbus','Penguin','Pixel','Breeze','Blossom','Grapes',
            'Rocket','Dandelion','Echo','Shadow','Nova','Sailor','Comet','Mango','Quartz','Mallow','Clover',
            'Whisker','Cloud','Whirl','Toffee','Sprout','Peach','Moon','Glider','Flame','Lemon',
            'Otter','Fern','Puddle','Drizzle','Hopper','Cactus','Star','Bean','Fable','Vine', 'Unicorn',
            'Meadow','Skipper','Ripple','Shell','Moth','Leaf','Spark','Dune','Frost','Blip', 'Puff'
        ];

        // Get the user_id for both Bleep and Comments models
        // This ensures the same user gets the same anonymous name everywhere
        if ($this instanceof \App\Models\Comments) {
            $userId = $this->user_id;
        } elseif ($this instanceof \App\Models\Bleep) {
            $userId = $this->user_id;
        } else {
            // Fallback for any other model
            $userId = $this->id;
        }

        // deterministic hash from user_id + viewer seed
        $hash = md5((string) $userId . '|' . (string) $viewerSeed);

        $firstIndex  = hexdec(substr($hash, 0, 8)) % count($firstParts);
        $secondIndex = hexdec(substr($hash, 8, 8)) % count($secondParts);

        return ucwords($firstParts[$firstIndex] . ' ' . $secondParts[$secondIndex]);
    }
}
