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

];
