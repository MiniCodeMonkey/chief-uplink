<?php

use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\WebSocketMessageBuffer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->buffer = new WebSocketMessageBuffer;
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->create([
        'is_online' => true,
    ]);
    $this->sessionId = 'test-session-'.uniqid();

    // Clean up any Redis keys from previous runs
    $this->cleanupKeys = [];
});

afterEach(function () {
    // Clean up Redis keys used in tests
    try {
        Redis::del("ws:buffer:{$this->device->id}:{$this->sessionId}");
        Redis::del("ws:sessions:{$this->device->id}");
        Redis::del("ws:disconnect:{$this->device->id}");
        Redis::zrem('ws:disconnected_devices', (string) $this->device->id);
    } catch (\Throwable) {
        // Redis not available, skip cleanup
    }
});

describe('Bufferable Message Types', function () {
    it('identifies bufferable message types', function () {
        $bufferableTypes = [
            'claude_output',
            'run_progress',
            'run_complete',
            'run_paused',
            'clone_progress',
            'session_timeout_warning',
            'error',
            'quota_exhausted',
        ];

        foreach ($bufferableTypes as $type) {
            expect($this->buffer->isBufferable($type))->toBeTrue("Expected '{$type}' to be bufferable");
        }
    });

    it('identifies non-bufferable message types', function () {
        $nonBufferableTypes = [
            'project_state',
            'project_list',
            'welcome',
            'auth_failed',
            'unknown',
        ];

        foreach ($nonBufferableTypes as $type) {
            expect($this->buffer->isBufferable($type))->toBeFalse("Expected '{$type}' to not be bufferable");
        }
    });
});

describe('Message Buffering', function () {
    it('buffers a bufferable message', function () {
        $message = ['type' => 'claude_output', 'payload' => ['text' => 'Hello world']];

        $result = $this->buffer->buffer($this->device->id, $this->sessionId, $message);

        expect($result)->toBeTrue();
        expect($this->buffer->getMessageCount($this->device->id, $this->sessionId))->toBe(1);
    });

    it('skips non-bufferable messages', function () {
        $message = ['type' => 'project_state', 'payload' => ['projects' => []]];

        $result = $this->buffer->buffer($this->device->id, $this->sessionId, $message);

        expect($result)->toBeFalse();
        expect($this->buffer->getMessageCount($this->device->id, $this->sessionId))->toBe(0);
    });

    it('skips messages without a type', function () {
        $message = ['payload' => ['text' => 'no type']];

        $result = $this->buffer->buffer($this->device->id, $this->sessionId, $message);

        expect($result)->toBeFalse();
    });

    it('buffers multiple messages in order', function () {
        $messages = [
            ['type' => 'run_progress', 'payload' => ['story' => 1]],
            ['type' => 'claude_output', 'payload' => ['text' => 'Working...']],
            ['type' => 'run_progress', 'payload' => ['story' => 2]],
        ];

        foreach ($messages as $msg) {
            $this->buffer->buffer($this->device->id, $this->sessionId, $msg);
        }

        expect($this->buffer->getMessageCount($this->device->id, $this->sessionId))->toBe(3);
    });

    it('tracks sessions for a device', function () {
        $session1 = 'session-1-'.uniqid();
        $session2 = 'session-2-'.uniqid();

        $this->buffer->buffer($this->device->id, $session1, ['type' => 'claude_output', 'payload' => []]);
        $this->buffer->buffer($this->device->id, $session2, ['type' => 'run_progress', 'payload' => []]);

        $allMessages = $this->buffer->replayAll($this->device->id);

        expect($allMessages)->toHaveCount(2);
        expect(array_keys($allMessages))->toContain($session1);
        expect(array_keys($allMessages))->toContain($session2);

        // Cleanup extra sessions
        Redis::del("ws:buffer:{$this->device->id}:{$session1}");
        Redis::del("ws:buffer:{$this->device->id}:{$session2}");
    });
});

describe('Message Replay', function () {
    it('replays buffered messages in order', function () {
        $messages = [
            ['type' => 'run_progress', 'payload' => ['story' => 1, 'status' => 'in_progress']],
            ['type' => 'claude_output', 'payload' => ['text' => 'Implementing feature...']],
            ['type' => 'run_progress', 'payload' => ['story' => 1, 'status' => 'completed']],
        ];

        foreach ($messages as $msg) {
            $this->buffer->buffer($this->device->id, $this->sessionId, $msg);
        }

        $replayed = $this->buffer->replay($this->device->id, $this->sessionId);

        expect($replayed)->toHaveCount(3);
        expect($replayed[0]['message']['type'])->toBe('run_progress');
        expect($replayed[0]['message']['payload']['story'])->toBe(1);
        expect($replayed[1]['message']['type'])->toBe('claude_output');
        expect($replayed[2]['message']['type'])->toBe('run_progress');
        expect($replayed[2]['message']['payload']['status'])->toBe('completed');
    });

    it('includes timestamps with each message', function () {
        $this->buffer->buffer($this->device->id, $this->sessionId, [
            'type' => 'claude_output',
            'payload' => ['text' => 'test'],
        ]);

        $replayed = $this->buffer->replay($this->device->id, $this->sessionId);

        expect($replayed[0])->toHaveKey('timestamp');
        expect($replayed[0]['timestamp'])->toBeFloat();
        expect($replayed[0]['timestamp'])->toBeGreaterThan(0);
    });

    it('returns empty array for non-existent session', function () {
        $replayed = $this->buffer->replay($this->device->id, 'non-existent-session');

        expect($replayed)->toBeEmpty();
    });

    it('replays all sessions for a device', function () {
        $session1 = 'replay-all-1-'.uniqid();
        $session2 = 'replay-all-2-'.uniqid();

        $this->buffer->buffer($this->device->id, $session1, ['type' => 'claude_output', 'payload' => ['text' => 'msg1']]);
        $this->buffer->buffer($this->device->id, $session2, ['type' => 'run_progress', 'payload' => ['story' => 1]]);

        $allMessages = $this->buffer->replayAll($this->device->id);

        expect($allMessages)->toHaveCount(2);
        expect($allMessages[$session1])->toHaveCount(1);
        expect($allMessages[$session2])->toHaveCount(1);
        expect($allMessages[$session1][0]['message']['type'])->toBe('claude_output');
        expect($allMessages[$session2][0]['message']['type'])->toBe('run_progress');

        // Cleanup
        Redis::del("ws:buffer:{$this->device->id}:{$session1}");
        Redis::del("ws:buffer:{$this->device->id}:{$session2}");
    });

    it('returns empty for device with no sessions', function () {
        $allMessages = $this->buffer->replayAll(99999);

        expect($allMessages)->toBeEmpty();
    });
});

describe('Buffer Flushing', function () {
    it('flushes a specific session', function () {
        $this->buffer->buffer($this->device->id, $this->sessionId, ['type' => 'claude_output', 'payload' => []]);

        expect($this->buffer->getMessageCount($this->device->id, $this->sessionId))->toBe(1);

        $this->buffer->flushSession($this->device->id, $this->sessionId);

        expect($this->buffer->getMessageCount($this->device->id, $this->sessionId))->toBe(0);
        expect($this->buffer->replay($this->device->id, $this->sessionId))->toBeEmpty();
    });

    it('flushes all sessions for a device', function () {
        $session1 = 'flush-all-1-'.uniqid();
        $session2 = 'flush-all-2-'.uniqid();

        $this->buffer->buffer($this->device->id, $session1, ['type' => 'claude_output', 'payload' => []]);
        $this->buffer->buffer($this->device->id, $session2, ['type' => 'run_progress', 'payload' => []]);

        $this->buffer->flushDevice($this->device->id);

        expect($this->buffer->replayAll($this->device->id))->toBeEmpty();
        expect($this->buffer->getMessageCount($this->device->id, $session1))->toBe(0);
        expect($this->buffer->getMessageCount($this->device->id, $session2))->toBe(0);
    });
});

describe('Disconnect and Reconnect Tracking', function () {
    it('marks a device as disconnected', function () {
        $this->buffer->markDisconnected($this->device->id);

        $timestamp = $this->buffer->getDisconnectTimestamp($this->device->id);

        expect($timestamp)->not->toBeNull();
        expect($timestamp)->toBeInt();
        expect(abs($timestamp - time()))->toBeLessThanOrEqual(2);
    });

    it('clears disconnect timestamp on reconnect', function () {
        $this->buffer->markDisconnected($this->device->id);
        expect($this->buffer->getDisconnectTimestamp($this->device->id))->not->toBeNull();

        $this->buffer->markReconnected($this->device->id);
        expect($this->buffer->getDisconnectTimestamp($this->device->id))->toBeNull();
    });

    it('returns null for device that has not disconnected', function () {
        $timestamp = $this->buffer->getDisconnectTimestamp(99999);

        expect($timestamp)->toBeNull();
    });
});

describe('Buffer Size Cap', function () {
    it('enforces buffer size cap by evicting oldest messages', function () {
        // Set a very small buffer cap for testing
        config(['websocket.buffer_max_size' => 200]);

        $buffer = new WebSocketMessageBuffer;

        // Buffer several messages that will exceed the cap
        for ($i = 0; $i < 20; $i++) {
            $buffer->buffer($this->device->id, $this->sessionId, [
                'type' => 'claude_output',
                'payload' => ['text' => "Message {$i} with some padding content to take up space"],
            ]);
        }

        // The buffer should have evicted some messages
        $size = $buffer->getBufferSize($this->device->id, $this->sessionId);
        expect($size)->toBeLessThanOrEqual(200);

        // The remaining messages should be the newest ones
        $replayed = $buffer->replay($this->device->id, $this->sessionId);
        expect(count($replayed))->toBeLessThan(20);

        // The last message should still be the newest
        if (! empty($replayed)) {
            $lastMessage = end($replayed);
            expect($lastMessage['message']['payload']['text'])->toContain('Message 19');
        }
    });

    it('does not evict when under the cap', function () {
        config(['websocket.buffer_max_size' => 5 * 1024 * 1024]); // 5MB

        $buffer = new WebSocketMessageBuffer;

        for ($i = 0; $i < 5; $i++) {
            $buffer->buffer($this->device->id, $this->sessionId, [
                'type' => 'claude_output',
                'payload' => ['text' => "Short message {$i}"],
            ]);
        }

        expect($buffer->getMessageCount($this->device->id, $this->sessionId))->toBe(5);
    });
});

describe('Buffer Size Tracking', function () {
    it('reports buffer size in bytes', function () {
        $this->buffer->buffer($this->device->id, $this->sessionId, [
            'type' => 'claude_output',
            'payload' => ['text' => 'Hello'],
        ]);

        $size = $this->buffer->getBufferSize($this->device->id, $this->sessionId);

        expect($size)->toBeGreaterThan(0);
    });

    it('reports zero for empty buffer', function () {
        $size = $this->buffer->getBufferSize($this->device->id, 'empty-session');

        expect($size)->toBe(0);
    });

    it('reports message count', function () {
        $this->buffer->buffer($this->device->id, $this->sessionId, ['type' => 'claude_output', 'payload' => []]);
        $this->buffer->buffer($this->device->id, $this->sessionId, ['type' => 'run_progress', 'payload' => []]);

        expect($this->buffer->getMessageCount($this->device->id, $this->sessionId))->toBe(2);
    });

    it('reports zero count for empty buffer', function () {
        expect($this->buffer->getMessageCount($this->device->id, 'empty-session'))->toBe(0);
    });
});

describe('Stale Buffer Cleanup', function () {
    it('cleans up buffers past grace period', function () {
        config(['websocket.buffer_grace_period' => 1]); // 1 second grace period

        $buffer = new WebSocketMessageBuffer;

        // Buffer a message and mark disconnected
        $buffer->buffer($this->device->id, $this->sessionId, [
            'type' => 'claude_output',
            'payload' => ['text' => 'stale message'],
        ]);

        // Mark disconnected, then backdate the timestamp to simulate time passing
        $buffer->markDisconnected($this->device->id);
        Redis::set("ws:disconnect:{$this->device->id}", (string) (time() - 10));
        Redis::zadd('ws:disconnected_devices', time() - 10, (string) $this->device->id);

        $cleaned = $buffer->cleanupStaleBuffers();

        expect($cleaned)->toBe(1);
        expect($buffer->getMessageCount($this->device->id, $this->sessionId))->toBe(0);
        expect($buffer->getDisconnectTimestamp($this->device->id))->toBeNull();
    });

    it('does not clean up buffers within grace period', function () {
        config(['websocket.buffer_grace_period' => 3600]); // 1 hour grace period

        $buffer = new WebSocketMessageBuffer;

        // Buffer a message and mark disconnected
        $buffer->buffer($this->device->id, $this->sessionId, [
            'type' => 'claude_output',
            'payload' => ['text' => 'fresh message'],
        ]);
        $buffer->markDisconnected($this->device->id);

        $cleaned = $buffer->cleanupStaleBuffers();

        expect($cleaned)->toBe(0);
        expect($buffer->getMessageCount($this->device->id, $this->sessionId))->toBe(1);
    });

    it('returns zero when no stale buffers exist', function () {
        $cleaned = $this->buffer->cleanupStaleBuffers();

        expect($cleaned)->toBe(0);
    });
});

describe('ServerConnectionManager Buffer Integration', function () {
    it('generates session ID on successful hello', function () {
        Event::fake();

        $manager = new App\Services\ServerConnectionManager;
        $token = generateBufferTestAccessToken($this->device);

        $result = $manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        expect($result['success'])->toBeTrue();
        expect($result['response'])->toHaveKey('session_id');
        expect($result['response']['session_id'])->not->toBeNull();
        expect($manager->getSessionId($this->device->id))->toBe($result['response']['session_id']);
    });

    it('buffers messages for authenticated devices', function () {
        Event::fake();

        $manager = new App\Services\ServerConnectionManager;
        $token = generateBufferTestAccessToken($this->device);

        $manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        $sessionId = $manager->getSessionId($this->device->id);

        $result = $manager->bufferMessage($this->device->id, [
            'type' => 'claude_output',
            'payload' => ['text' => 'test output'],
        ]);

        expect($result)->toBeTrue();

        // Verify the message was buffered
        $buffer = app(WebSocketMessageBuffer::class);
        $messages = $buffer->replay($this->device->id, $sessionId);
        expect($messages)->toHaveCount(1);
        expect($messages[0]['message']['type'])->toBe('claude_output');

        // Cleanup
        $buffer->flushDevice($this->device->id);
    });

    it('does not buffer for unauthenticated devices', function () {
        $manager = new App\Services\ServerConnectionManager;

        $result = $manager->bufferMessage(999, [
            'type' => 'claude_output',
            'payload' => ['text' => 'test'],
        ]);

        expect($result)->toBeFalse();
    });

    it('marks device disconnected in buffer on disconnect', function () {
        Event::fake();

        $manager = new App\Services\ServerConnectionManager;
        $token = generateBufferTestAccessToken($this->device);

        $manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        $manager->handleDisconnect(1);

        $buffer = app(WebSocketMessageBuffer::class);
        $timestamp = $buffer->getDisconnectTimestamp($this->device->id);
        expect($timestamp)->not->toBeNull();
        expect(abs($timestamp - time()))->toBeLessThanOrEqual(2);

        // Cleanup
        $buffer->flushDevice($this->device->id);
    });

    it('clears disconnect timestamp on reconnect', function () {
        Event::fake();

        $manager = new App\Services\ServerConnectionManager;
        $token = generateBufferTestAccessToken($this->device);

        // Connect, then disconnect
        $manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);
        $manager->handleDisconnect(1);

        $buffer = app(WebSocketMessageBuffer::class);
        expect($buffer->getDisconnectTimestamp($this->device->id))->not->toBeNull();

        // Reconnect
        $manager->handleHello(2, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        expect($buffer->getDisconnectTimestamp($this->device->id))->toBeNull();

        // Cleanup
        $buffer->flushDevice($this->device->id);
    });
});

describe('Replay API Endpoint', function () {
    it('replays messages for an authorized device', function () {
        // Buffer some messages first
        $buffer = app(WebSocketMessageBuffer::class);
        $sessionId = 'api-test-'.uniqid();

        $buffer->buffer($this->device->id, $sessionId, [
            'type' => 'claude_output',
            'payload' => ['text' => 'Hello from buffer'],
        ]);
        $buffer->buffer($this->device->id, $sessionId, [
            'type' => 'run_progress',
            'payload' => ['story' => 1],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/ws/buffer/replay', [
                'device_id' => $this->device->id,
                'session_id' => $sessionId,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'device_id',
            'session_id',
            'messages' => [
                '*' => ['message', 'timestamp'],
            ],
        ]);

        $data = $response->json();
        expect($data['messages'])->toHaveCount(2);
        expect($data['messages'][0]['message']['type'])->toBe('claude_output');
        expect($data['messages'][1]['message']['type'])->toBe('run_progress');

        // Cleanup
        $buffer->flushDevice($this->device->id);
    });

    it('replays all sessions when no session_id provided', function () {
        $buffer = app(WebSocketMessageBuffer::class);
        $session1 = 'api-all-1-'.uniqid();
        $session2 = 'api-all-2-'.uniqid();

        $buffer->buffer($this->device->id, $session1, ['type' => 'claude_output', 'payload' => []]);
        $buffer->buffer($this->device->id, $session2, ['type' => 'run_progress', 'payload' => []]);

        $response = $this->actingAs($this->user)
            ->postJson('/ws/buffer/replay', [
                'device_id' => $this->device->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'device_id',
            'sessions',
        ]);

        $data = $response->json();
        expect($data['sessions'])->toHaveCount(2);

        // Cleanup
        $buffer->flushDevice($this->device->id);
    });

    it('rejects replay for unauthorized device', function () {
        $otherUser = User::factory()->create();
        $otherDevice = DeviceAuthorization::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)
            ->postJson('/ws/buffer/replay', [
                'device_id' => $otherDevice->id,
            ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Device not found or not authorized.']);
    });

    it('rejects replay for revoked device', function () {
        $revokedDevice = DeviceAuthorization::factory()->for($this->user)->revoked()->create();

        $response = $this->actingAs($this->user)
            ->postJson('/ws/buffer/replay', [
                'device_id' => $revokedDevice->id,
            ]);

        $response->assertStatus(403);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/ws/buffer/replay', [
            'device_id' => $this->device->id,
        ]);

        $response->assertUnauthorized();
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/ws/buffer/replay', []);

        $response->assertStatus(422);
    });
});

describe('Cleanup Artisan Command', function () {
    it('runs the ws:buffer:cleanup command', function () {
        $this->artisan('ws:buffer:cleanup')
            ->assertSuccessful()
            ->expectsOutputToContain('Cleaned up');
    });
});

// Helper for generating test access tokens
function generateBufferTestAccessToken(DeviceAuthorization $device, int $expiresIn = 3600): string
{
    $payload = [
        'sub' => $device->user_id,
        'did' => $device->id,
        'iat' => time(),
        'exp' => time() + $expiresIn,
    ];

    $payloadJson = json_encode($payload);
    $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));

    return $payloadBase64.'.'.$signature;
}
