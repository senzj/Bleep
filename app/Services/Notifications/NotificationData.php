<?php

namespace App\Services\Notifications;

use App\Models\Bleep;
use App\Models\Comments;

/**
 * Builds the small render snapshot stored on a notification so the inbox does
 * not need to load the bleep/comment (which may be deleted later).
 */
class NotificationData
{
    /**
     * @return array{excerpt: ?string, anonymous: bool}
     */
    public static function bleep(Bleep $bleep): array
    {
        return [
            'excerpt' => self::excerpt($bleep->message),
            'anonymous' => (bool) $bleep->is_anonymous,
        ];
    }

    /**
     * @return array{excerpt: ?string, anonymous: bool}
     */
    public static function comment(Comments $comment): array
    {
        return [
            'excerpt' => self::excerpt($comment->message),
            'anonymous' => (bool) $comment->is_anonymous,
        ];
    }

    public static function excerpt(?string $text, int $limit = 120): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > $limit
            ? mb_substr($text, 0, $limit - 1).'…'
            : $text;
    }
}
