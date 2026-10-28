<?php

namespace App\Helpers;

/**
 * Shared word pools for anonymous display names.
 *
 * The order of these lists is part of the existing anonymous-name algorithm.
 * Append new words at the end rather than reordering or removing entries.
 */
class AnonymousName
{
    /**
     * @return list<string>
     */
    public static function firstParts(): array
    {
        return [
            'Rampage', 'Clam', 'Sunny', 'Brave', 'Sneaky', 'Mighty', 'Quiet', 'Spicy', 'Fuzzy', 'Neon', 'Apple', 'Cotton',
            'Turbo', 'Happy', 'Icy', 'Rusty', 'Velvet', 'Silver', 'Crimson', 'Jolly', 'Gloomy', 'Zen', 'Cherry', 'Marsh',
            'Clever', 'Blissful', 'Frosty', 'Witty', 'Cosmic', 'Lucky', 'Stormy', 'Mellow', 'Amber', 'Dizzy',
            'Chilly', 'Dreamy', 'Zesty', 'Golden', 'Peachy', 'Twisty', 'Snazzy', 'Shadowy', 'Electric', 'Plush', 'Fluffy',
            'Dusty', 'Tiny', 'Rowdy', 'Snappy', 'Bubbly', 'Sleepy', 'Wild', 'Royal', 'Cheerful', 'Mystic', 'Joyful', 'Sparky',
            'Silly', 'Gleaming', 'Breezy', 'Crisp', 'Glowing', 'Jazzy', 'Lively', 'Merry', 'Nifty', 'Quirky', 'Radiant',
            'Sassy', 'Shiny', 'Sleek', 'Snug', 'Spunky', 'Vibrant', 'Witty', 'Zany', 'Dapper', 'Feisty', 'Giddy', 'Humble',
            'Jolly', 'Kooky', 'Luminous', 'Mischievous', 'Noble', 'Peppy', 'Quixotic', 'Rascal', 'Sleek', 'Sprightly', 'Twinkling',
            'Waggish', 'Zesty', 'Bouncy', 'Cheeky', 'Dazzling', 'Effervescent', 'Frisky', 'Gleeful', 'Hilarious', 'Inventive', 'Jubilant', 'Kaleidoscopic',
        ];
    }

    /**
     * @return list<string>
     */
    public static function secondParts(): array
    {
        return [
            'Berry', 'Banana', 'Fox', 'Tiger', 'Pancake', 'Nimbus', 'Penguin', 'Pixel', 'Breeze', 'Blossom', 'Grapes',
            'Rocket', 'Dandelion', 'Echo', 'Shadow', 'Nova', 'Sailor', 'Comet', 'Mango', 'Quartz', 'Mallow', 'Clover',
            'Whisker', 'Cloud', 'Whirl', 'Toffee', 'Sprout', 'Peach', 'Moon', 'Glider', 'Flame', 'Lemon',
            'Otter', 'Fern', 'Puddle', 'Drizzle', 'Hopper', 'Cactus', 'Star', 'Bean', 'Fable', 'Vine', 'Unicorn',
            'Meadow', 'Skipper', 'Ripple', 'Shell', 'Moth', 'Leaf', 'Spark', 'Dune', 'Frost', 'Blip', 'Puff', 'Daisy', 'Waffle',
            'Puffin', 'Sparrow', 'Turtle', 'Wombat', 'Zebra', 'Yarn', 'Zucchini', 'Velociraptor', 'Narwhal', 'Bee', 'Gnome',
            'Llama', 'Pony', 'Raccoon', 'Sloth', 'Taco', 'Waffle', 'Yeti', 'Zither', 'Dragon', 'Phoenix', 'Griffin', 'Unicorn',
            'Pegasus', 'Mermaid', 'Centaur', 'Sphinx', 'Chimera', 'Kraken', 'Minotaur', 'Hydra', 'Basilisk', 'Gargoyle', 'Fairy',
            'Elf', 'Dwarf', 'Goblin', 'Orc', 'Troll', 'Vampire', 'Werewolf', 'Zombie', 'Mummy', 'Ghost', 'Witch', 'Warlock', 'Sorcerer', 'Necromancer',
        ];
    }

    /**
     * Generate a short anonymous display name such as "LittleGold128" or
     * "BigLaser53". Shared by the bulletin board and the username generator.
     */
    public static function randomDisplayName(): string
    {
        $firstParts = self::firstParts();
        $secondParts = self::secondParts();

        $first = $firstParts[array_rand($firstParts)];
        $second = $secondParts[array_rand($secondParts)];

        return ucfirst(strtolower($first))
            .ucfirst(strtolower($second))
            .random_int(0, 999);
    }

    /**
     * Generate an anonymous bulletin board identity.
     */
    public static function randomBulletinIdentity(): string
    {
        return self::randomDisplayName();
    }
}
