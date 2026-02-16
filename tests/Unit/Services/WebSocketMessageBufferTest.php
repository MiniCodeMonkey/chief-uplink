<?php

use App\Services\WebSocketMessageBuffer;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->buffer = new WebSocketMessageBuffer;
    $this->deviceId = 1;
    $this->sessionId = 'test-session-123';

    // Clean up Redis keys from previous tests using the buffer's own flush method
    $this->buffer->flushDevice($this->deviceId);

    // Also clean up additional keys that flushDevice may not cover
    $redis = Redis::connection();
    $redis->del([
        'ws:buffer:1:session-a',
        'ws:buffer:1:session-b',
        'ws:sessions:1',
        'ws:disconnect:1',
    ]);
    $redis->zrem('ws:disconnected_devices', '1');
});

test('isBufferable returns true for bufferable types', function () {
    foreach (WebSocketMessageBuffer::BUFFERABLE_TYPES as $type) {
        expect($this->buffer->isBufferable($type))->toBeTrue();
    }
});

test('isBufferable returns false for non-bufferable types', function () {
    expect($this->buffer->isBufferable('project_state'))->toBeFalse();
    expect($this->buffer->isBufferable('project_list'))->toBeFalse();
    expect($this->buffer->isBufferable('unknown_type'))->toBeFalse();
});

test('buffer stores bufferable message', function () {
    $result = $this->buffer->buffer($this->deviceId, $this->sessionId, [
        'type' => 'claude_output',
        'text' => 'Hello world',
    ]);

    expect($result)->toBeTrue();
    expect($this->buffer->getMessageCount($this->deviceId, $this->sessionId))->toBe(1);
});

test('buffer skips non-bufferable message', function () {
    $result = $this->buffer->buffer($this->deviceId, $this->sessionId, [
        'type' => 'project_state',
        'data' => [],
    ]);

    expect($result)->toBeFalse();
    expect($this->buffer->getMessageCount($this->deviceId, $this->sessionId))->toBe(0);
});

test('buffer skips message without type', function () {
    $result = $this->buffer->buffer($this->deviceId, $this->sessionId, [
        'data' => 'no type field',
    ]);

    expect($result)->toBeFalse();
});

test('replay returns buffered messages in order', function () {
    $this->buffer->buffer($this->deviceId, $this->sessionId, [
        'type' => 'claude_output',
        'text' => 'first',
    ]);
    $this->buffer->buffer($this->deviceId, $this->sessionId, [
        'type' => 'claude_output',
        'text' => 'second',
    ]);

    $messages = $this->buffer->replay($this->deviceId, $this->sessionId);

    expect($messages)->toHaveCount(2);
    expect($messages[0]['message']['text'])->toBe('first');
    expect($messages[1]['message']['text'])->toBe('second');
});

test('replay returns empty array for empty buffer', function () {
    $messages = $this->buffer->replay($this->deviceId, $this->sessionId);
    expect($messages)->toBeEmpty();
});

test('replay includes timestamps', function () {
    $this->buffer->buffer($this->deviceId, $this->sessionId, [
        'type' => 'run_progress',
        'progress' => 50,
    ]);

    $messages = $this->buffer->replay($this->deviceId, $this->sessionId);

    expect($messages[0])->toHaveKey('timestamp');
    expect($messages[0]['timestamp'])->toBeFloat();
});

test('flushSession removes buffer for specific session', function () {
    $this->buffer->buffer($this->deviceId, $this->sessionId, [
        'type' => 'claude_output',
        'text' => 'test',
    ]);

    $this->buffer->flushSession($this->deviceId, $this->sessionId);

    expect($this->buffer->getMessageCount($this->deviceId, $this->sessionId))->toBe(0);
});

test('flushDevice removes all buffers for device', function () {
    $this->buffer->buffer($this->deviceId, 'session-a', [
        'type' => 'claude_output',
        'text' => 'test-a',
    ]);
    $this->buffer->buffer($this->deviceId, 'session-b', [
        'type' => 'claude_output',
        'text' => 'test-b',
    ]);

    $this->buffer->flushDevice($this->deviceId);

    expect($this->buffer->getMessageCount($this->deviceId, 'session-a'))->toBe(0);
    expect($this->buffer->getMessageCount($this->deviceId, 'session-b'))->toBe(0);
});

test('replayAll returns messages from all sessions', function () {
    $this->buffer->buffer($this->deviceId, 'session-a', [
        'type' => 'claude_output',
        'text' => 'from-a',
    ]);
    $this->buffer->buffer($this->deviceId, 'session-b', [
        'type' => 'run_progress',
        'progress' => 75,
    ]);

    $all = $this->buffer->replayAll($this->deviceId);

    expect($all)->toHaveCount(2);
    expect($all)->toHaveKeys(['session-a', 'session-b']);
});

test('markDisconnected and getDisconnectTimestamp', function () {
    $this->buffer->markDisconnected($this->deviceId);

    $timestamp = $this->buffer->getDisconnectTimestamp($this->deviceId);
    expect($timestamp)->not->toBeNull();
    expect($timestamp)->toBeInt();
    expect(abs($timestamp - time()))->toBeLessThanOrEqual(2);
});

test('markReconnected clears disconnect timestamp', function () {
    $this->buffer->markDisconnected($this->deviceId);
    $this->buffer->markReconnected($this->deviceId);

    expect($this->buffer->getDisconnectTimestamp($this->deviceId))->toBeNull();
});

test('getBufferSize returns size in bytes', function () {
    $this->buffer->buffer($this->deviceId, $this->sessionId, [
        'type' => 'claude_output',
        'text' => 'some content here',
    ]);

    $size = $this->buffer->getBufferSize($this->deviceId, $this->sessionId);
    expect($size)->toBeGreaterThan(0);
});

test('getBufferSize returns 0 for empty buffer', function () {
    expect($this->buffer->getBufferSize($this->deviceId, $this->sessionId))->toBe(0);
});

test('cleanupStaleBuffers removes old buffers', function () {
    // Create buffer and mark as disconnected
    $this->buffer->buffer($this->deviceId, $this->sessionId, [
        'type' => 'claude_output',
        'text' => 'old data',
    ]);

    // Manually set disconnect timestamp to the past
    $redis = Redis::connection();
    $cutoff = time() - 600; // 10 minutes ago
    $redis->set("ws:disconnect:{$this->deviceId}", (string) $cutoff);
    $redis->zadd('ws:disconnected_devices', $cutoff, (string) $this->deviceId);

    $cleaned = $this->buffer->cleanupStaleBuffers();

    expect($cleaned)->toBe(1);
    expect($this->buffer->getMessageCount($this->deviceId, $this->sessionId))->toBe(0);
});

test('cleanupStaleBuffers does not remove active buffers', function () {
    $this->buffer->buffer($this->deviceId, $this->sessionId, [
        'type' => 'claude_output',
        'text' => 'current data',
    ]);
    $this->buffer->markDisconnected($this->deviceId);

    // Buffer was just disconnected, grace period has not passed
    $cleaned = $this->buffer->cleanupStaleBuffers();

    expect($cleaned)->toBe(0);
    expect($this->buffer->getMessageCount($this->deviceId, $this->sessionId))->toBe(1);
});

test('bufferable types includes expected types', function () {
    $expected = [
        'claude_output', 'run_progress', 'run_complete', 'run_paused',
        'clone_progress', 'session_timeout_warning', 'session_expired',
        'error', 'quota_exhausted', 'prd_output', 'prd_response_complete',
    ];

    foreach ($expected as $type) {
        expect(WebSocketMessageBuffer::BUFFERABLE_TYPES)->toContain($type);
    }
});
