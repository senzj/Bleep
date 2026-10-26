<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Social = 'social';
    case Content = 'content';
    case Follow = 'follow';
    case Moderation = 'moderation';
    case Account = 'account';
    case Visits = 'visits';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Social => 'Likes, reposts and shares',
            self::Content => 'Comments and replies',
            self::Follow => 'Follows and requests',
            self::Moderation => 'Moderation',
            self::Account => 'Account and security',
            self::Visits => 'Profile visits',
            self::System => 'Administration',
        };
    }
}
