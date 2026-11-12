<?php

namespace App\Enums;

enum BulletinReactionType: string
{
    case Heart = 'heart';
    case ThumbsUp = 'thumbs_up';
    case Laugh = 'laugh';
    case Wow = 'wow';
    case Fire = 'fire';
    case Sad = 'sad';
    case Angry = 'angry';
    case Cry = 'cry';
    case Flower = 'flower';

    public function emoji(): string
    {
        return match ($this) {
            self::Heart => '❤️',
            self::ThumbsUp => '👍',
            self::Laugh => '😂',
            self::Wow => '😮',
            self::Fire => '🔥',
            self::Sad => '😞',
            self::Angry => '😠',
            self::Cry => '😭',
            self::Flower => '🌸',
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
            self::Sad => 'Sad',
            self::Angry => 'Angry',
            self::Cry => 'Cry',
            self::Flower => 'Flower',
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
