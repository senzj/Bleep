<?php

namespace App\Enums;

enum BulletinReactionType: string
{
    case Heart = 'heart';
    case ThumbsUp = 'thumbs_up';
    case Laugh = 'laugh';
    case Wow = 'wow';
    case Fire = 'fire';
    case Idea = 'idea';

    public function emoji(): string
    {
        return match ($this) {
            self::Heart => '❤️',
            self::ThumbsUp => '👍',
            self::Laugh => '😂',
            self::Wow => '😮',
            self::Fire => '🔥',
            self::Idea => '💡',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Heart => 'Love',
            self::ThumbsUp => 'Like',
            self::Laugh => 'Funny',
            self::Wow => 'Surprised',
            self::Fire => 'Fire',
            self::Idea => 'Idea',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
