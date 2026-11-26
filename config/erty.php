<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First installation (php artisan erty:install)
    |--------------------------------------------------------------------------
    |
    | The first administrator signs in with this email: sign-in sends a 6-digit
    | code there, so use a mailbox you can read. The business is named after
    | BUSINESS_NAME, or the app name when that is empty.
    |
    */

    'admin_email' => env('ADMIN_EMAIL'),

    'business_name' => env('BUSINESS_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Support tickets
    |--------------------------------------------------------------------------
    |
    | Files people can add to a ticket and its replies. They are kept on the
    | private "local" disk (storage/app/support) and only downloaded through
    | the app, by the person who posted the ticket and the administrators.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Activity log
    |--------------------------------------------------------------------------
    |
    | Who opened, changed or downloaded what in the business app, and sign-ins.
    | Entries older than this many days are removed every night
    | (php artisan activity:prune).
    |
    */

    'activity_log' => [
        'enabled' => true, // the access audit switches it off while it signs in as each person
        'keep_days' => (int) env('ACTIVITY_LOG_KEEP_DAYS', 365),
    ],

    'support' => [
        'attachments' => [
            'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'doc', 'docx', 'txt'],
            'max_kb' => 10240,
            'per_ticket' => 5,
        ],
    ],

];
