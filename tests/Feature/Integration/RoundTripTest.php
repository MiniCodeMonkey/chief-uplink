<?php

use App\Events\ChiefCommandDispatched;
use App\Events\ChiefMessageReceived;
use App\Events\DeviceConnected;
use App\Models\DeviceAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->create([
        'device_name' => 'roundtrip-device',
        'is_online' => false,
    ]);
    $this->token = generateRoundTripToken($this->device);
});

function generateRoundTripToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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

function connectDevice(mixed $test, string $token): string
{
    $response = $test->postJson('/api/device/connect', [
        'chief_version' => '1.0.0',
        'device_name' => 'roundtrip-device',
        'os' => 'darwin',
        'arch' => 'arm64',
        'protocol_version' => 1,
    ], ['Authorization' => 'Bearer '.$token]);

    $response->assertOk();

    return $response->json('session_id');
}

/*
|--------------------------------------------------------------------------
| Full Round-Trip: Browser → Server → CLI → Server → Browser
|--------------------------------------------------------------------------
| Tests the complete bidirectional flow:
| 1. Browser sends command via CommandRelayController
| 2. Server broadcasts ChiefCommandDispatched to CLI channel
| 3. CLI processes and sends response via message ingestion
| 4. Server broadcasts ChiefMessageReceived to browser channel
|
| Verifies channels, event names, and payloads match frontend expectations.
*/

test('full round-trip: browser command → CLI response', function () {
    Event::fake([DeviceConnected::class, ChiefCommandDispatched::class, ChiefMessageReceived::class]);

    connectDevice($this, $this->token);

    // Step 1: Browser sends start_run command
    $this->actingAs($this->user)
        ->postJson("/ws/command/{$this->device->id}", [
            'type' => 'start_run',
            'payload' => [
                'project_slug' => 'my-project',
                'prd_id' => 'feature-auth',
            ],
        ])->assertOk()->assertJson([
            'status' => 'sent',
            'type' => 'start_run',
            'device_id' => $this->device->id,
        ]);

    // Step 2: Verify command dispatched to CLI channel
    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
        // Verify channel is private chief-server channel for this device
        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1);
        expect($channels[0]->name)->toBe("private-chief-server.{$this->device->id}");

        // Verify event name matches frontend expectation
        expect($event->broadcastAs())->toBe('chief.command');

        // Verify payload structure
        $payload = $event->broadcastWith();
        expect($payload['type'])->toBe('start_run');
        expect($payload['payload']['project_slug'])->toBe('my-project');
        expect($payload['payload']['prd_id'])->toBe('feature-auth');

        return true;
    });

    // Step 3: CLI sends run_progress response via message ingestion
    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            [
                'type' => 'run_progress',
                'id' => 'msg-1',
                'payload' => [
                    'project_slug' => 'my-project',
                    'prd_id' => 'feature-auth',
                    'status' => 'in_progress',
                    'story_index' => 0,
                ],
            ],
        ],
    ], ['Authorization' => 'Bearer '.$this->token])->assertOk();

    // Step 4: Verify response broadcast to browser channel
    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        // Verify channel is private device channel for browser
        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1);
        expect($channels[0]->name)->toBe("private-device.{$this->device->id}");

        // Verify event name matches frontend expectation
        expect($event->broadcastAs())->toBe('chief.message');

        // Verify payload structure
        $payload = $event->broadcastWith();
        expect($payload)->toHaveKeys(['device_id', 'type', 'payload', 'message']);
        expect($payload['device_id'])->toBe($this->device->id);
        expect($payload['type'])->toBe('run_progress');
        expect($payload['payload']['status'])->toBe('in_progress');

        return true;
    });
});

/*
|--------------------------------------------------------------------------
| PRD Round-Trip: new_prd → prd_output → prd_message → prd_response_complete
|--------------------------------------------------------------------------
| Tests the PRD chat flow from start to finish.
*/

test('PRD round-trip: create → output → follow-up → complete', function () {
    Event::fake([DeviceConnected::class, ChiefCommandDispatched::class, ChiefMessageReceived::class]);

    connectDevice($this, $this->token);

    // Step 1: Browser sends new_prd command
    $this->actingAs($this->user)
        ->postJson("/ws/command/{$this->device->id}", [
            'type' => 'new_prd',
            'payload' => [
                'project_slug' => 'my-project',
                'description' => 'Build user authentication',
            ],
        ])->assertOk();

    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
        return $event->command['type'] === 'new_prd'
            && $event->broadcastAs() === 'chief.command';
    });

    // Step 2: CLI sends prd_output back
    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            [
                'type' => 'prd_output',
                'id' => 'prd-1',
                'payload' => ['content' => '# Authentication PRD\n\n## Overview\n...'],
            ],
        ],
    ], ['Authorization' => 'Bearer '.$this->token])->assertOk();

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->message['type'] === 'prd_output'
            && $event->broadcastAs() === 'chief.message';
    });

    // Step 3: Browser sends follow-up prd_message
    $this->actingAs($this->user)
        ->postJson("/ws/command/{$this->device->id}", [
            'type' => 'prd_message',
            'payload' => ['message' => 'Add OAuth2 support'],
        ])->assertOk();

    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
        return $event->command['type'] === 'prd_message';
    });

    // Step 4: CLI sends final response
    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            [
                'type' => 'prd_response_complete',
                'id' => 'prd-2',
                'payload' => ['prd_id' => 'auth-feature'],
            ],
        ],
    ], ['Authorization' => 'Bearer '.$this->token])->assertOk();

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->message['type'] === 'prd_response_complete';
    });
});

/*
|--------------------------------------------------------------------------
| Run Lifecycle Round-Trip: start → progress → complete
|--------------------------------------------------------------------------
| Tests a Ralph run from start to completion.
*/

test('run lifecycle round-trip: start → progress → complete', function () {
    Event::fake([DeviceConnected::class, ChiefCommandDispatched::class, ChiefMessageReceived::class]);

    connectDevice($this, $this->token);

    // Browser starts run
    $this->actingAs($this->user)
        ->postJson("/ws/command/{$this->device->id}", [
            'type' => 'start_run',
            'payload' => ['project_slug' => 'api-service', 'prd_id' => 'crud-endpoints'],
        ])->assertOk();

    // CLI sends progress updates
    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            ['type' => 'run_progress', 'id' => 'rp-1', 'payload' => ['status' => 'in_progress', 'story_index' => 0]],
            ['type' => 'claude_output', 'id' => 'co-1', 'payload' => ['text' => 'Working on story 1...']],
        ],
    ], ['Authorization' => 'Bearer '.$this->token])->assertOk();

    // CLI sends completion
    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            ['type' => 'run_complete', 'id' => 'rc-1', 'payload' => ['stories_completed' => 3, 'stories_total' => 3]],
        ],
    ], ['Authorization' => 'Bearer '.$this->token])->assertOk();

    // Verify all events dispatched correctly
    Event::assertDispatchedTimes(ChiefCommandDispatched::class, 1);
    Event::assertDispatchedTimes(ChiefMessageReceived::class, 3);

    // Verify each message reached the browser channel
    Event::assertDispatched(ChiefMessageReceived::class, fn ($e) => $e->message['type'] === 'run_progress');
    Event::assertDispatched(ChiefMessageReceived::class, fn ($e) => $e->message['type'] === 'claude_output');
    Event::assertDispatched(ChiefMessageReceived::class, fn ($e) => $e->message['type'] === 'run_complete');
});

/*
|--------------------------------------------------------------------------
| Channel Isolation: Commands and Responses Use Different Channels
|--------------------------------------------------------------------------
| Commands go to chief-server.{deviceId} (CLI listens),
| Responses go to device.{deviceId} (browser listens).
*/

test('commands and responses use distinct channels', function () {
    Event::fake([DeviceConnected::class, ChiefCommandDispatched::class, ChiefMessageReceived::class]);

    connectDevice($this, $this->token);

    // Browser sends command
    $this->actingAs($this->user)
        ->postJson("/ws/command/{$this->device->id}", [
            'type' => 'get_settings',
            'payload' => ['project_slug' => 'my-project'],
        ])->assertOk();

    // CLI sends response
    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            ['type' => 'settings_response', 'id' => 's-1', 'payload' => ['settings' => []]],
        ],
    ], ['Authorization' => 'Bearer '.$this->token])->assertOk();

    // Verify channels are distinct
    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
        return $event->broadcastOn()[0]->name === "private-chief-server.{$this->device->id}";
    });

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->broadcastOn()[0]->name === "private-device.{$this->device->id}";
    });
});

/*
|--------------------------------------------------------------------------
| Multiple Command Types Flow Through Correctly
|--------------------------------------------------------------------------
| Tests that all valid command types relay to the CLI channel.
*/

test('all relay command types dispatch to CLI channel', function () {
    Event::fake([DeviceConnected::class, ChiefCommandDispatched::class]);

    connectDevice($this, $this->token);

    $commandTypes = [
        'start_run' => ['project_slug' => 'p'],
        'pause_run' => ['run_id' => 'r1'],
        'resume_run' => ['run_id' => 'r1'],
        'stop_run' => ['run_id' => 'r1'],
        'get_settings' => ['project_slug' => 'p'],
        'update_settings' => ['project_slug' => 'p'],
        'get_logs' => ['project_slug' => 'p'],
        'get_diffs' => ['project_slug' => 'p'],
        'get_prds' => ['project_slug' => 'p'],
    ];

    foreach ($commandTypes as $type => $payload) {
        $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => $type,
                'payload' => $payload,
            ])->assertOk();
    }

    Event::assertDispatchedTimes(ChiefCommandDispatched::class, count($commandTypes));
});
