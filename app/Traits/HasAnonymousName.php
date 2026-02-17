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
            'Dusty','Tiny','Rowdy','Snappy','Bubbly','Sleepy','Wild','Royal','Cheerful','Mystic', 'Joyful', 'Sparky',
            'Silly', 'Gleaming', 'Breezy', 'Crisp', 'Glowing', 'Jazzy', 'Lively', 'Merry', 'Nifty', 'Quirky', 'Radiant',
            'Sassy', 'Shiny', 'Sleek', 'Snug', 'Spunky', 'Vibrant', 'Witty', 'Zany', 'Dapper', 'Feisty', 'Giddy', 'Humble',
            'Jolly', 'Kooky', 'Luminous', 'Mischievous', 'Noble', 'Peppy', 'Quixotic', 'Rascal', 'Sleek', 'Sprightly', 'Twinkling',
            'Waggish', 'Zesty', 'Bouncy', 'Cheeky', 'Dazzling', 'Effervescent', 'Frisky', 'Gleeful', 'Hilarious', 'Inventive', 'Jubilant', 'Kaleidoscopic',
        ];

        $secondParts = [
            'Berry','Banana','Fox','Tiger','Pancake','Nimbus','Penguin','Pixel','Breeze','Blossom','Grapes',
            'Rocket','Dandelion','Echo','Shadow','Nova','Sailor','Comet','Mango','Quartz','Mallow','Clover',
            'Whisker','Cloud','Whirl','Toffee','Sprout','Peach','Moon','Glider','Flame','Lemon',
            'Otter','Fern','Puddle','Drizzle','Hopper','Cactus','Star','Bean','Fable','Vine', 'Unicorn',
            'Meadow','Skipper','Ripple','Shell','Moth','Leaf','Spark','Dune','Frost','Blip', 'Puff', 'Daisy', 'Waffle',
            'Puffin', 'Sparrow', 'Turtle', 'Wombat', 'Zebra', 'Yarn', 'Zucchini', 'Velociraptor', 'Narwhal', 'Bee', 'Gnome',
            'Llama', 'Pony', 'Raccoon', 'Sloth', 'Taco', 'Waffle', 'Yeti', 'Zither', 'Dragon', 'Phoenix', 'Griffin', 'Unicorn',
            'Pegasus', 'Mermaid', 'Centaur', 'Sphinx', 'Chimera', 'Kraken', 'Minotaur', 'Hydra', 'Basilisk', 'Gargoyle', 'Fairy',
            'Elf', 'Dwarf', 'Goblin', 'Orc', 'Troll', 'Vampire', 'Werewolf', 'Zombie', 'Mummy', 'Ghost', 'Witch', 'Warlock', 'Sorcerer', 'Necromancer',
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

    /**
     * Generate a random username with 1-3 random digits inserted.
     * Digits can be inserted between the first and second parts or at the end.
     * Perfect for users wanting a unique, randomized username.
     */
    public static function generateRandomUsername(): string
    {
        $firstParts = [
            'Rampage','Clam','Sunny','Brave','Sneaky','Mighty','Quiet','Spicy','Fuzzy','Neon','Apple','Cotton',
            'Turbo','Happy','Icy','Rusty','Velvet','Silver','Crimson','Jolly','Gloomy','Zen','Cherry','Marsh',
            'Clever','Blissful','Frosty','Witty','Cosmic','Lucky','Stormy','Mellow','Amber','Dizzy',
            'Chilly','Dreamy','Zesty','Golden','Peachy','Twisty','Snazzy','Shadowy','Electric','Plush', 'Fluffy',
            'Dusty','Tiny','Rowdy','Snappy','Bubbly','Sleepy','Wild','Royal','Cheerful','Mystic', 'Joyful', 'Sparky',
            'Silly', 'Gleaming', 'Breezy', 'Crisp', 'Glowing', 'Jazzy', 'Lively', 'Merry', 'Nifty', 'Quirky', 'Radiant',
            'Sassy', 'Shiny', 'Sleek', 'Snug', 'Spunky', 'Vibrant', 'Witty', 'Zany', 'Dapper', 'Feisty', 'Giddy', 'Humble',
            'Jolly', 'Kooky', 'Luminous', 'Mischievous', 'Noble', 'Peppy', 'Quixotic', 'Rascal', 'Sleek', 'Sprightly', 'Twinkling',
            'Waggish', 'Zesty', 'Bouncy', 'Cheeky', 'Dazzling', 'Effervescent', 'Frisky', 'Gleeful', 'Hilarious', 'Inventive', 'Jubilant', 'Kaleidoscopic',
        ];

        $secondParts = [
            'Berry','Banana','Fox','Tiger','Pancake','Nimbus','Penguin','Pixel','Breeze','Blossom','Grapes',
            'Rocket','Dandelion','Echo','Shadow','Nova','Sailor','Comet','Mango','Quartz','Mallow','Clover',
            'Whisker','Cloud','Whirl','Toffee','Sprout','Peach','Moon','Glider','Flame','Lemon',
            'Otter','Fern','Puddle','Drizzle','Hopper','Cactus','Star','Bean','Fable','Vine', 'Unicorn',
            'Meadow','Skipper','Ripple','Shell','Moth','Leaf','Spark','Dune','Frost','Blip', 'Puff', 'Daisy', 'Waffle',
            'Puffin', 'Sparrow', 'Turtle', 'Wombat', 'Zebra', 'Yarn', 'Zucchini', 'Velociraptor', 'Narwhal', 'Bee', 'Gnome',
            'Llama', 'Pony', 'Raccoon', 'Sloth', 'Taco', 'Waffle', 'Yeti', 'Zither', 'Dragon', 'Phoenix', 'Griffin', 'Unicorn',
            'Pegasus', 'Mermaid', 'Centaur', 'Sphinx', 'Chimera', 'Kraken', 'Minotaur', 'Hydra', 'Basilisk', 'Gargoyle', 'Fairy',
            'Elf', 'Dwarf', 'Goblin', 'Orc', 'Troll', 'Vampire', 'Werewolf', 'Zombie', 'Mummy', 'Ghost', 'Witch', 'Warlock', 'Sorcerer', 'Necromancer',
        ];

        // Pick random parts
        $first = $firstParts[array_rand($firstParts)];
        $second = $secondParts[array_rand($secondParts)];

        // Generate 1-3 random digits
        $digitCount = rand(1, 3);
        $randomDigits = '';
        for ($i = 0; $i < $digitCount; $i++) {
            $randomDigits .= rand(0, 9);
        }

        // Randomly decide: add digits between parts (50%) or at the end (50%)
        if (rand(0, 1) === 0) {
            // Add between: FirstPart123Second
            return strtolower($first . $randomDigits . $second);
        } else {
            // Add at end: FirstPartSecond123
            return strtolower($first . $second . $randomDigits);
        }
    }
}
