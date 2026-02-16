<?php

use App\Models\DeviceAuthorization;
use App\Models\LogCache;

test('log cache belongs to device authorization', function () {
    $device = DeviceAuthorization::factory()->create();
    $log = LogCache::factory()->for($device)->create();

    expect($log->deviceAuthorization->id)->toBe($device->id);
});

test('log cache uses custom table name', function () {
    $log = new LogCache;
    expect($log->getTable())->toBe('log_cache');
});

test('log cache factory creates valid record', function () {
    $log = LogCache::factory()->create();

    expect($log->project_slug)->not->toBeNull();
    expect($log->log_type)->toBe('claude_output');
    expect($log->story_id)->toMatch('/^US-\d{3}$/');
    expect($log->content)->not->toBeEmpty();
});
