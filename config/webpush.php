<?php

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:hello@chiefuplink.com'),
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],
];
