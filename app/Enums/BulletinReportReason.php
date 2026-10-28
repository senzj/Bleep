<?php

namespace App\Enums;

enum BulletinReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Unsafe = 'unsafe';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam or advertising',
            self::Harassment => 'Harassment or hate',
            self::Unsafe => 'Unsafe link or image',
            self::Other => 'Something else',
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
