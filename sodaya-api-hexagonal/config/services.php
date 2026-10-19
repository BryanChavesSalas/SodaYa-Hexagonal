<?php

declare(strict_types=1);

return [

    /*
    | Stripe in test mode, by direct HTTP: the secret key must start with sk_test_, and the base URL
    | only changes to point the adapter at a local mock of the API.
    */
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
    ],

];
