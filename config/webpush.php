<?php

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:noreply@chiefloop.com'),
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],

    // Max push notifications per user per hour
    'rate_limit' => (int) env('PUSH_NOTIFICATION_RATE_LIMIT', 20),
];
