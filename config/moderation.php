<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Strike escalation
    |--------------------------------------------------------------------------
    |
    | A strike is an active warning (issued, not revoked, not expired). When a
    | user reaches this many active strikes they are banned automatically with
    | the standard suspension ladder. Set to 0 to disable the auto-ban.
    |
    */
    'auto_ban_at_strikes' => (int) env('MODERATION_AUTO_BAN_STRIKES', 3),

    /*
    |--------------------------------------------------------------------------
    | Warning lifetime
    |--------------------------------------------------------------------------
    |
    | How long a warning keeps counting as a strike. A rolling window avoids
    | punishing a user forever for an old mistake.
    |
    */
    'warning_expiry_days' => (int) env('MODERATION_WARNING_EXPIRY_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Warning reasons
    |--------------------------------------------------------------------------
    */
    'warning_reasons' => [
        'spam',
        'harassment',
        'hate',
        'nsfw',
        'illegal',
        'other',
    ],
];
