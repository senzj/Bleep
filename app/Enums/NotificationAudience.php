<?php

namespace App\Enums;

enum NotificationAudience: string
{
    case User = 'user';
    case Moderator = 'moderator';
    case Admin = 'admin';

    /**
     * Map a user's role to the audience their staff notifications belong to.
     */
    public static function fromRole(?string $role): self
    {
        return match ($role) {
            'admin' => self::Admin,
            'moderator' => self::Moderator,
            default => self::User,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::User => 'Activity',
            self::Moderator => 'Moderation',
            self::Admin => 'Administration',
        };
    }
}
