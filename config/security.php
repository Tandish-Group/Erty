<?php

return [

    /*
    | Strict-Transport-Security header, sent on HTTPS responses (App\Http\Middleware\HSTS).
    */
    'hsts' => [
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => (bool) env('HSTS_PRELOAD', true),
    ],

    /*
    | Requests for URLs that do not exist (App\Http\Middleware\ValidateUrl): an IP address that makes
    | more than `max_attempts` such requests within `decay_minutes` is blocked.
    */
    'url_guard' => [
        'max_attempts' => (int) env('MAX_ATTEMPTS', 2),
        'decay_minutes' => (int) env('DECAY_MINUTES', 1),
    ],

];
