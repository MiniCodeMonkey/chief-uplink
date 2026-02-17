<?php

use App\Events\ChiefCommandDispatched;
use App\Events\ChiefMessageReceived;
use App\Events\DeviceConnected;
use App\Events\DeviceDisconnected;
use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\WebSocketMessageBuffer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->create([
        'device_name' => 'e2e-device',
        'is_online' => false,
    ]);
});

function generateE2eToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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

/*
|--------------------------------------------------------------------------
| CLI → Server → Browser Flow
|--------------------------------------------------------------------------
| Tests the full message flow: CLI sends batched messages via HTTP POST,
| server processes them and broadcasts to the browser channel.
*/

test('CLI sends batched messages and server broadcasts to browser', function () {
    Event::fake([DeviceConnected::class, ChiefMessageReceived::class]);

    $token = generateE2eToken($this->device);

    // Step 1: CLI connects
    $connectResponse = $this->postJson('/api/device/connect', [
        'chief_version' => '1.0.0',
        'device_name' => 'e2e-device',
        'os' => 'darwin',
        'arch' => 'arm64',
        'protocol_version' => 1,
    ], ['Authorization' => 'Bearer '.$token]);

    $connectResponse->assertOk()->assertJson(['type' => 'welcome']);
    $sessionId = $connectResponse->json('session_id');

    // Step 2: CLI sends message batch
    $batchId = Str::uuid()->toString();
    $messagesResponse = $this->postJson('/api/device/messages', [
        'batch_id' => $batchId,
        'messages' => [
            ['type' => 'claude_output', 'id' => 'msg-1', 'payload' => ['text' => 'Working on US-001']],
            ['type' => 'run_progress', 'id' => 'msg-2', 'payload' => ['status' => 'in_progress']],
        ],
    ], ['Authorization' => 'Bearer '.$token]);

    $messagesResponse->assertOk()->assertJson([
        'accepted' => 2,
        'batch_id' => $batchId,
        'session_id' => $sessionId,
    ]);

    // Step 3: Verify server broadcast each message to browser channel
    Event::assertDispatchedTimes(ChiefMessageReceived::class, 2);

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->deviceId === $this->device->id
            && $event->userId === $this->user->id
            && $event->message['type'] === 'claude_output'
            && $event->message['payload']['text'] === 'Working on US-001';
    });

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->message['type'] === 'run_progress'
            && $event->message['payload']['status'] === 'in_progress';
    });
});

/*
|--------------------------------------------------------------------------
| Server → CLI Flow
|--------------------------------------------------------------------------
| Tests the command relay: browser sends command via CommandRelayController,
| server broadcasts ChiefCommandDispatched to CLI channel.
*/

test('browser sends command and server broadcasts to CLI channel', function () {
    Event::fake([DeviceConnected::class, ChiefCommandDispatched::class]);

    $token = generateE2eToken($this->device);

    // Step 1: CLI connects (makes device online)
    $this->postJson('/api/device/connect', [
        'chief_version' => '1.0.0',
        'protocol_version' => 1,
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    // Step 2: Browser sends command to device
    $response = $this->actingAs($this->user)
        ->postJson("/ws/command/{$this->device->id}", [
            'type' => 'start_run',
            'payload' => ['project_slug' => 'my-project', 'prd_id' => 'feature-1'],
        ]);

    $response->assertOk()->assertJson([
        'status' => 'sent',
        'type' => 'start_run',
        'device_id' => $this->device->id,
    ]);

    // Step 3: Verify ChiefCommandDispatched event was broadcast for CLI
    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
        return $event->deviceId === $this->device->id
            && $event->userId === $this->user->id
            && $event->command['type'] === 'start_run'
            && $event->command['payload']['project_slug'] === 'my-project';
    });
});

/*
|--------------------------------------------------------------------------
| Device Lifecycle
|--------------------------------------------------------------------------
| Tests the complete device lifecycle: connect → steady state (messages
| + heartbeat) → graceful disconnect.
*/

test('device lifecycle: connect → messages + heartbeat → disconnect', function () {
    Event::fake([DeviceConnected::class, DeviceDisconnected::class, ChiefMessageReceived::class]);

    $token = generateE2eToken($this->device);

    // Step 1: Connect
    $connectResponse = $this->postJson('/api/device/connect', [
        'chief_version' => '1.0.0',
        'protocol_version' => 1,
    ], ['Authorization' => 'Bearer '.$token]);

    $connectResponse->assertOk()->assertJson(['type' => 'welcome']);

    $this->device->refresh();
    expect($this->device->is_online)->toBeTrue()
        ->and($this->device->session_id)->not->toBeNull();

    Event::assertDispatched(DeviceConnected::class);

    // Step 2: Send messages
    $batchId = Str::uuid()->toString();
    $this->postJson('/api/device/messages', [
        'batch_id' => $batchId,
        'messages' => [
            ['type' => 'state_snapshot', 'id' => 'msg-1', 'payload' => ['projects' => []]],
        ],
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    // Step 3: Send heartbeat
    $this->postJson('/api/device/heartbeat', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk()->assertJson(['status' => 'ok']);

    $this->device->refresh();
    expect($this->device->last_heartbeat_at)->not->toBeNull();

    // Step 4: Graceful disconnect
    $disconnectResponse = $this->postJson('/api/device/disconnect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $disconnectResponse->assertOk()->assertJson(['status' => 'disconnected']);

    $this->device->refresh();
    expect($this->device->is_online)->toBeFalse()
        ->and($this->device->session_id)->not->toBeNull(); // session_id preserved for buffer

    Event::assertDispatched(DeviceDisconnected::class);
});

/*
|--------------------------------------------------------------------------
| Ungraceful Disconnect Detection
|--------------------------------------------------------------------------
| Tests that the server detects ungraceful disconnect via stale heartbeat
| and marks the device offline within the expected timeframe.
*/

test('ungraceful disconnect detected via stale heartbeat', function () {
    Event::fake([DeviceConnected::class, DeviceDisconnected::class, ChiefMessageReceived::class]);

    $token = generateE2eToken($this->device);

    // Step 1: Connect
    $this->postJson('/api/device/connect', [
        'chief_version' => '1.0.0',
        'protocol_version' => 1,
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    $this->device->refresh();
    expect($this->device->is_online)->toBeTrue();

    // Step 2: Simulate crash — heartbeat goes stale (3 minutes ago)
    $this->device->update(['last_heartbeat_at' => now()->subMinutes(3)]);

    // Step 3: Run heartbeat checker
    $this->artisan('device:check-heartbeats')->assertSuccessful();

    // Step 4: Verify device marked offline
    $this->device->refresh();
    expect($this->device->is_online)->toBeFalse();

    Event::assertDispatched(DeviceDisconnected::class, function ($event) {
        return $event->deviceId === $this->device->id;
    });
});

/*
|--------------------------------------------------------------------------
| Reconnection Flow
|--------------------------------------------------------------------------
| Tests that a device can reconnect after disconnect: new session_id,
| buffer continuity handled.
*/

test('device reconnects with new session after disconnect', function () {
    Event::fake([DeviceConnected::class, DeviceDisconnected::class, ChiefMessageReceived::class]);

    $mock = $this->mock(WebSocketMessageBuffer::class);
    $mock->shouldReceive('markReconnected')->twice()->with($this->device->id);
    $mock->shouldReceive('markDisconnected')->once()->with($this->device->id);

    $token = generateE2eToken($this->device);

    // Step 1: First connect
    $response1 = $this->postJson('/api/device/connect', [
        'protocol_version' => 1,
    ], ['Authorization' => 'Bearer '.$token]);

    $response1->assertOk();
    $session1 = $response1->json('session_id');

    // Step 2: Send some messages
    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [['type' => 'claude_output', 'id' => 'msg-1', 'payload' => ['text' => 'Hello']]],
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    // Step 3: Disconnect
    $this->postJson('/api/device/disconnect', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $this->device->refresh();
    expect($this->device->is_online)->toBeFalse();

    // Step 4: Reconnect
    $response2 = $this->postJson('/api/device/connect', [
        'protocol_version' => 1,
    ], ['Authorization' => 'Bearer '.$token]);

    $response2->assertOk();
    $session2 = $response2->json('session_id');

    // New session should be different
    expect($session2)->not->toBe($session1);

    $this->device->refresh();
    expect($this->device->is_online)->toBeTrue()
        ->and($this->device->session_id)->toBe($session2);

    // Step 5: Continue sending with new session
    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [['type' => 'claude_output', 'id' => 'msg-2', 'payload' => ['text' => 'Back online']]],
    ], ['Authorization' => 'Bearer '.$token])->assertOk();
});

/*
|--------------------------------------------------------------------------
| PRD Session Bidirectional Flow
|--------------------------------------------------------------------------
| Tests PRD-related commands flowing from browser to CLI and PRD output
| flowing from CLI to browser.
*/

test('PRD session: browser sends command, CLI sends output', function () {
    Event::fake([DeviceConnected::class, ChiefCommandDispatched::class, ChiefMessageReceived::class]);

    $token = generateE2eToken($this->device);

    // Step 1: CLI connects
    $this->postJson('/api/device/connect', [
        'chief_version' => '1.0.0',
        'protocol_version' => 1,
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    // Step 2: Browser sends new_prd command → CLI via broadcast
    $this->actingAs($this->user)
        ->postJson("/ws/command/{$this->device->id}", [
            'type' => 'new_prd',
            'payload' => ['project_slug' => 'my-project', 'description' => 'Build auth'],
        ])->assertOk();

    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
        return $event->command['type'] === 'new_prd';
    });

    // Step 3: CLI sends prd_output back → Browser via broadcast
    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            ['type' => 'prd_output', 'id' => 'prd-1', 'payload' => ['content' => 'Generated PRD content']],
        ],
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->message['type'] === 'prd_output'
            && $event->message['payload']['content'] === 'Generated PRD content';
    });

    // Step 4: Browser sends prd_message (follow-up) → CLI
    $this->actingAs($this->user)
        ->postJson("/ws/command/{$this->device->id}", [
            'type' => 'prd_message',
            'payload' => ['message' => 'Add authentication section'],
        ])->assertOk();

    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
        return $event->command['type'] === 'prd_message';
    });
});

/*
|--------------------------------------------------------------------------
| Idempotency
|--------------------------------------------------------------------------
| Tests that retrying a batch with the same batch_id doesn't duplicate
| processing.
*/

test('idempotent retry: same batch_id does not duplicate processing', function () {
    Event::fake([DeviceConnected::class, ChiefMessageReceived::class]);

    $token = generateE2eToken($this->device);

    // Connect the device
    $this->postJson('/api/device/connect', [
        'protocol_version' => 1,
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    $batchId = Str::uuid()->toString();
    $payload = [
        'batch_id' => $batchId,
        'messages' => [
            ['type' => 'claude_output', 'id' => 'msg-1', 'payload' => ['text' => 'Hello']],
            ['type' => 'run_progress', 'id' => 'msg-2', 'payload' => ['status' => 'running']],
        ],
    ];
    $headers = ['Authorization' => 'Bearer '.$token];

    // First request
    $response1 = $this->postJson('/api/device/messages', $payload, $headers);
    $response1->assertOk()->assertJson(['accepted' => 2, 'batch_id' => $batchId]);

    Event::assertDispatchedTimes(ChiefMessageReceived::class, 2);

    // Simulated retry (same batch_id) — should return cached response
    $response2 = $this->postJson('/api/device/messages', $payload, $headers);
    $response2->assertOk()->assertJson(['accepted' => 2, 'batch_id' => $batchId]);

    // Should NOT broadcast again
    Event::assertDispatchedTimes(ChiefMessageReceived::class, 2);
});

/*
|--------------------------------------------------------------------------
| Implicit Heartbeat via Message Ingestion
|--------------------------------------------------------------------------
| Tests that sending messages resets the heartbeat timer so the CLI
| can skip explicit heartbeat calls when actively sending.
*/

test('message ingestion resets heartbeat timer and prevents stale detection', function () {
    Event::fake([DeviceConnected::class, DeviceDisconnected::class, ChiefMessageReceived::class]);

    $token = generateE2eToken($this->device);

    // Connect
    $this->postJson('/api/device/connect', [
        'protocol_version' => 1,
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    // Set heartbeat to stale time
    $this->device->update(['last_heartbeat_at' => now()->subMinutes(3)]);

    // Send a message — should update heartbeat
    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [['type' => 'claude_output', 'id' => 'msg-1', 'payload' => []]],
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    // Now run heartbeat checker — device should NOT be marked offline
    $this->artisan('device:check-heartbeats')->assertSuccessful();

    $this->device->refresh();
    expect($this->device->is_online)->toBeTrue();

    Event::assertNotDispatched(DeviceDisconnected::class);
});

/*
|--------------------------------------------------------------------------
| Project State Updates Through Full Stack
|--------------------------------------------------------------------------
| Tests that project_state messages update the cached project state
| and are broadcast to the browser.
*/

test('project state updates cached state without broadcasting', function () {
    Event::fake([DeviceConnected::class, ChiefMessageReceived::class]);

    $token = generateE2eToken($this->device);

    // Connect
    $this->postJson('/api/device/connect', [
        'protocol_version' => 1,
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    // Send project_state message
    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            [
                'type' => 'project_state',
                'payload' => [
                    'projects' => [
                        [
                            'project_slug' => 'my-project',
                            'project_name' => 'My Project',
                            'status' => 'running',
                            'git_branch' => 'feat/auth',
                            'stories_completed' => 5,
                            'stories_total' => 10,
                        ],
                    ],
                ],
            ],
        ],
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    // Verify cached state was updated
    $cached = CachedProjectState::where('device_authorization_id', $this->device->id)
        ->where('project_slug', 'my-project')
        ->first();

    expect($cached)->not->toBeNull()
        ->and($cached->project_name)->toBe('My Project')
        ->and($cached->status)->toBe('running')
        ->and($cached->stories_completed)->toBe(5);

    // Server-only types are NOT broadcast (payload exceeds Reverb's 10KB limit)
    Event::assertNotDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->message['type'] === 'project_state';
    });
});

/*
|--------------------------------------------------------------------------
| Multiple Device Isolation
|--------------------------------------------------------------------------
| Tests that commands are isolated to the correct device — one device's
| commands don't affect another.
*/

test('commands are isolated between devices', function () {
    Event::fake([DeviceConnected::class, ChiefCommandDispatched::class]);

    $device2 = DeviceAuthorization::factory()->for($this->user)->create([
        'device_name' => 'e2e-device-2',
        'is_online' => false,
    ]);

    $token1 = generateE2eToken($this->device);
    $token2 = generateE2eToken($device2);

    // Connect both devices
    $this->postJson('/api/device/connect', ['protocol_version' => 1], [
        'Authorization' => 'Bearer '.$token1,
    ])->assertOk();

    $this->postJson('/api/device/connect', ['protocol_version' => 1], [
        'Authorization' => 'Bearer '.$token2,
    ])->assertOk();

    // Send command to device 1 only
    $this->actingAs($this->user)
        ->postJson("/ws/command/{$this->device->id}", [
            'type' => 'start_run',
            'payload' => ['project_slug' => 'project-a'],
        ])->assertOk();

    // Verify command dispatched with device 1's ID
    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
        return $event->deviceId === $this->device->id;
    });

    // Verify NO command dispatched with device 2's ID
    Event::assertNotDispatched(ChiefCommandDispatched::class, function ($event) use ($device2) {
        return $event->deviceId === $device2->id;
    });
});

/*
|--------------------------------------------------------------------------
| Offline Device Cannot Receive Commands
|--------------------------------------------------------------------------
| Tests that the server rejects commands to offline devices.
*/

test('browser cannot send commands to offline device', function () {
    Event::fake([DeviceConnected::class, DeviceDisconnected::class, ChiefCommandDispatched::class]);

    $token = generateE2eToken($this->device);

    // Connect then disconnect
    $this->postJson('/api/device/connect', ['protocol_version' => 1], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $this->postJson('/api/device/disconnect', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    // Browser tries to send command
    $response = $this->actingAs($this->user)
        ->postJson("/ws/command/{$this->device->id}", [
            'type' => 'start_run',
            'payload' => [],
        ]);

    $response->assertStatus(503)->assertJson(['error' => 'server_offline']);

    // No command should be dispatched
    Event::assertNotDispatched(ChiefCommandDispatched::class);
});
