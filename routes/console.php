<?php

use App\Services\WebSocketMessageBuffer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ws:buffer:cleanup', function () {
    $buffer = app(WebSocketMessageBuffer::class);
    $cleaned = $buffer->cleanupStaleBuffers();
    $this->info("Cleaned up {$cleaned} stale WebSocket buffer(s).");
})->purpose('Clean up stale WebSocket message buffers that have exceeded the grace period');

Schedule::command('ws:buffer:cleanup')->everyMinute();
