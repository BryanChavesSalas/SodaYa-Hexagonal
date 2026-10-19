<?php

declare(strict_types=1);

return [

    /*
    | Time of day, in Costa Rica, when every dish gets back its base portions.
    | Pending confirmation with the client (P-07), so it can change without touching the code.
    */
    'daily_portions_reset_at' => env('DAILY_PORTIONS_RESET_AT', '05:00'),

    /*
    | Channel the notices to the customer go out through: "log" or "mail".
    | An unknown value stops the first notice loudly instead of notifying nobody in silence.
    */
    'notice_channel' => env('NOTICE_CHANNEL', 'log'),

    /*
    | Seconds the public menu of a soda stays in the cache. It is a safety net: an edit of the owner
    | invalidates the menu at once by raising its version, so this only bounds how long a lost
    | invalidation can serve stale data.
    */
    'menu_cache_seconds' => (int) env('MENU_CACHE_SECONDS', 600),

];
