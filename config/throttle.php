<?php

/*
| Request limits for the web routes (App\Http\Middleware\DynamicThrottle): [requests, per minutes].
| The first matching path wins. Signed-in people are counted per person, so a team working from one office
| address does not share a limit; anyone else is counted per IP address.
| The public listings API has its own limit (LISTINGS_API_RATE_LIMIT).
*/
return [
    'rate_limits' => [
        // Sign-in and password recovery: tight, against guessing
        'login' => [20, 1],
        'auth' => [20, 1],
        'validateOTP' => [20, 1],
        'resend' => [5, 1],
        'password/email' => [10, 1],
        'password/reset*' => [10, 1],
        'password/confirm' => [10, 1],
    ],

    // Every other page and the requests pages make in the background
    'signed_in' => [600, 1],
    'guest' => [60, 1],
];
