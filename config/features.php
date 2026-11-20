<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature switches
    |--------------------------------------------------------------------------
    |
    | Features can be turned off for a deployment (for example shared hosting
    | without Reverb support). Navigation entries for disabled features are
    | hidden so users never reach a dead end.
    |
    */

    'chat' => (bool) env('FEATURE_CHAT_ENABLED', true),

    /*
    | Chance that a rendered page of bleeps includes the Support the project
    | card. Every tab, page and infinite-scroll batch rolls separately, so
    | 0.2 shows the card on roughly one in five renders.
    */
    'support_promo_chance' => (float) env('FEATURE_SUPPORT_PROMO_CHANCE', 0.2),

    /*
    | Where the support card sends people. Set FEATURE_SUPPORT_URL to your own
    | donation page; the default is the project's GitHub Sponsors page.
    */
    'support_url' => env('FEATURE_SUPPORT_URL', 'https://github.com/sponsors/senzj'),

];
