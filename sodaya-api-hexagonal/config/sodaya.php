<?php

declare(strict_types=1);

return [

    /*
    | Time of day, in Costa Rica, when every dish gets back its base portions.
    | Pending confirmation with the client (P-07), so it can change without touching the code.
    */
    'daily_portions_reset_at' => env('DAILY_PORTIONS_RESET_AT', '05:00'),

];
