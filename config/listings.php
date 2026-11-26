<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public listings API (GET /api/v1/listings)
    |--------------------------------------------------------------------------
    |
    | Read-only, no sign-in. Requests per minute per IP address, and page sizes.
    |
    */

    'api' => [
        'rate_limit' => (int) env('LISTINGS_API_RATE_LIMIT', 60),
        'per_page' => 20,
        'max_per_page' => 50,
    ],

];
