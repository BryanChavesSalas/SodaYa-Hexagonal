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

];
