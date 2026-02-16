<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WebSocket Buffer Max Size
    |--------------------------------------------------------------------------
    |
    | Maximum buffer size per session in bytes. Oldest messages are evicted
    | when this cap is exceeded. Default: 5MB (5,242,880 bytes).
    |
    */

    'buffer_max_size' => (int) env('WS_BUFFER_MAX_SIZE', 5 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | WebSocket Buffer Grace Period
    |--------------------------------------------------------------------------
    |
    | Number of seconds to keep the buffer after a chief server disconnects.
    | After this period, the buffer is flushed by the cleanup task.
    | Default: 300 seconds (5 minutes).
    |
    */

    'buffer_grace_period' => (int) env('WS_BUFFER_GRACE_PERIOD', 300),

    /*
    |--------------------------------------------------------------------------
    | PRD Session Timeout
    |--------------------------------------------------------------------------
    |
    | Number of seconds of inactivity before a PRD chat session expires.
    | Default: 1800 seconds (30 minutes).
    |
    */

    'prd_session_timeout' => (int) env('PRD_SESSION_TIMEOUT', 1800),

];
