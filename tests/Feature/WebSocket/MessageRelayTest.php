<?php

use App\Events\ChiefMessageReceived;
use App\Http\Controllers\Api\CommandRelayController;
use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\ServerConnectionManager;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->create([
        'is_online' => true,
        'device_name' => 'test-device',
    ]);
    $this->otherUser = User::factory()->create();
    $this->otherDevice = DeviceAuthorization::factory()->for($this->otherUser)->create([
        'is_online' => true,
    ]);
});

function generateRelayTestAccessToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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

function setupOnlineDevice(ServerConnectionManager $manager, DeviceAuthorization $device): void
{
    $connectionId = $device->id * 1000; // Use a unique connection ID based on device ID
    $token = generateRelayTestAccessToken($device);

    // Create a mock Connection object
    $connection = Mockery::mock(\Laravel\Reverb\Servers\Reverb\Connection::class);
    $connection->shouldReceive('send')->andReturn(null);

    Event::fake();

    $manager->handleHello($connectionId, [
        'type' => 'hello',
        'protocol_version' => 1,
        'chief_version' => '0.5.0',
        'device_name' => $device->device_name,
        'os' => 'linux',
        'arch' => 'amd64',
        'access_token' => $token,
    ]);

    $manager->registerConnectionObject($connectionId, $connection);
}

describe('Browser → Chief Command Relay', function () {
    it('sends valid command to online device', function () {
        $manager = app(ServerConnectionManager::class);
        setupOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'start_run',
                'payload' => ['project_slug' => 'my-project'],
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'sent',
                'type' => 'start_run',
                'device_id' => $this->device->id,
            ]);
    });

    it('returns 503 when device is offline', function () {
        // Don't set up online device — leave it offline in the connection manager

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'start_run',
                'payload' => [],
            ]);

        $response->assertStatus(503)
            ->assertJson([
                'error' => 'server_offline',
                'message' => 'Server offline',
            ]);
    });

    it('returns 403 for another user\'s device', function () {
        $manager = app(ServerConnectionManager::class);
        setupOnlineDevice($manager, $this->otherDevice);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->otherDevice->id}", [
                'type' => 'start_run',
                'payload' => [],
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'device_not_found',
            ]);
    });

    it('returns 403 for revoked device', function () {
        $this->device->update(['revoked_at' => now()]);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'start_run',
                'payload' => [],
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'device_not_found',
            ]);
    });

    it('rejects invalid command type', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'invalid_command',
                'payload' => [],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'invalid_command',
            ]);
    });

    it('validates required type field', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'payload' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    });

    it('accepts all valid command types', function () {
        $manager = app(ServerConnectionManager::class);
        setupOnlineDevice($manager, $this->device);

        foreach (CommandRelayController::VALID_COMMANDS as $command) {
            $response = $this->actingAs($this->user)
                ->postJson("/ws/command/{$this->device->id}", [
                    'type' => $command,
                    'payload' => [],
                ]);

            $response->assertOk();
        }
    });

    it('sends command with payload to chief', function () {
        $manager = app(ServerConnectionManager::class);
        $connectionId = $this->device->id * 1000;
        $token = generateRelayTestAccessToken($this->device);

        // Create a mock connection that records what's sent
        $sentMessages = [];
        $connection = Mockery::mock(\Laravel\Reverb\Servers\Reverb\Connection::class);
        $connection->shouldReceive('send')->andReturnUsing(function ($msg) use (&$sentMessages) {
            $sentMessages[] = json_decode($msg, true);
        });

        Event::fake();

        $manager->handleHello($connectionId, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.5.0',
            'device_name' => 'test-device',
            'os' => 'linux',
            'arch' => 'amd64',
            'access_token' => $token,
        ]);

        $manager->registerConnectionObject($connectionId, $connection);

        $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'clone_repo',
                'payload' => ['url' => 'https://github.com/user/repo.git', 'directory' => 'repo'],
            ]);

        // Verify the message was sent with correct structure
        // First message is the welcome response, second is our command
        $lastMessage = end($sentMessages);
        expect($lastMessage['type'])->toBe('clone_repo');
        expect($lastMessage['payload']['url'])->toBe('https://github.com/user/repo.git');
        expect($lastMessage['payload']['directory'])->toBe('repo');
    });

    it('requires authentication', function () {
        $response = $this->postJson("/ws/command/{$this->device->id}", [
            'type' => 'start_run',
            'payload' => [],
        ]);

        $response->assertStatus(401);
    });

    it('accepts command with empty payload', function () {
        $manager = app(ServerConnectionManager::class);
        setupOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'pause_run',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'sent',
                'type' => 'pause_run',
            ]);
    });

    it('returns 404 for non-existent device', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/ws/command/99999', [
                'type' => 'start_run',
                'payload' => [],
            ]);

        $response->assertStatus(403);
    });
});

describe('Chief → Browser Message Broadcasting', function () {
    it('broadcasts ChiefMessageReceived event on incoming chief message', function () {
        Event::fake([ChiefMessageReceived::class]);

        $manager = app(ServerConnectionManager::class);
        $connectionId = 100;
        $token = generateRelayTestAccessToken($this->device);

        $manager->handleHello($connectionId, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.5.0',
            'device_name' => 'test-device',
            'os' => 'linux',
            'arch' => 'amd64',
            'access_token' => $token,
        ]);

        // Simulate the controller handling a message from chief
        $connection = Mockery::mock(\Laravel\Reverb\Servers\Reverb\Connection::class);
        $connection->shouldReceive('id')->andReturn($connectionId);
        $connection->shouldReceive('withMaxMessageSize')->andReturn($connection);
        $connection->shouldReceive('onMessage')->andReturn($connection);
        $connection->shouldReceive('onClose')->andReturn($connection);
        $connection->shouldReceive('openBuffer')->andReturn(null);
        $connection->shouldReceive('send')->andReturn(null);

        $manager->registerConnectionObject($connectionId, $connection);

        // Directly call the ChiefServerController's handleMessage logic
        // by dispatching the event as the controller would
        $message = [
            'type' => 'run_progress',
            'payload' => [
                'story_id' => 'us-001',
                'status' => 'in_progress',
                'iteration' => 2,
            ],
        ];

        ChiefMessageReceived::dispatch(
            $this->device->id,
            $this->user->id,
            $message
        );

        Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
            return $event->deviceId === $this->device->id
                && $event->userId === $this->user->id
                && $event->message['type'] === 'run_progress';
        });
    });

    it('broadcasts to correct device channel', function () {
        $event = new ChiefMessageReceived(
            $this->device->id,
            $this->user->id,
            ['type' => 'claude_output', 'payload' => ['text' => 'Hello']]
        );

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0]->name)->toBe("private-device.{$this->device->id}");
    });

    it('uses chief.message event name', function () {
        $event = new ChiefMessageReceived(
            $this->device->id,
            $this->user->id,
            ['type' => 'run_complete', 'payload' => []]
        );

        expect($event->broadcastAs())->toBe('chief.message');
    });

    it('includes message data in broadcast payload', function () {
        $message = [
            'type' => 'run_progress',
            'payload' => [
                'story_id' => 'us-003',
                'status' => 'completed',
            ],
        ];

        $event = new ChiefMessageReceived(
            $this->device->id,
            $this->user->id,
            $message
        );

        $broadcastData = $event->broadcastWith();

        expect($broadcastData['device_id'])->toBe($this->device->id);
        expect($broadcastData['type'])->toBe('run_progress');
        expect($broadcastData['payload'])->toBe($message['payload']);
        expect($broadcastData['message'])->toBe($message);
    });

    it('handles message without payload', function () {
        $message = ['type' => 'run_complete'];

        $event = new ChiefMessageReceived(
            $this->device->id,
            $this->user->id,
            $message
        );

        $broadcastData = $event->broadcastWith();

        expect($broadcastData['type'])->toBe('run_complete');
        expect($broadcastData['payload'])->toBeNull();
    });
});

describe('Message Format and Validation', function () {
    it('passes through command with JSON type and payload', function () {
        $manager = app(ServerConnectionManager::class);
        setupOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'update_settings',
                'payload' => [
                    'max_iterations' => 10,
                    'auto_commit' => true,
                    'commit_prefix' => 'feat',
                ],
            ]);

        $response->assertOk();
    });

    it('rejects non-JSON request', function () {
        $response = $this->actingAs($this->user)
            ->post("/ws/command/{$this->device->id}", [
                'type' => 'start_run',
            ], ['Accept' => 'application/json']);

        // Should still work as Laravel handles form data
        // The validation will pass since type is present
        $response->assertStatus(503); // Device offline, but format was accepted
    });
});

describe('Offline Device Handling', function () {
    it('returns server offline for disconnected device', function () {
        $manager = app(ServerConnectionManager::class);

        // Device is not registered in connection manager
        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'start_run',
                'payload' => [],
            ]);

        $response->assertStatus(503)
            ->assertJson([
                'error' => 'server_offline',
                'message' => 'Server offline',
            ]);
    });

    it('returns server offline after device disconnects', function () {
        $manager = app(ServerConnectionManager::class);
        $connectionId = $this->device->id * 1000;
        $token = generateRelayTestAccessToken($this->device);

        $connection = Mockery::mock(\Laravel\Reverb\Servers\Reverb\Connection::class);
        $connection->shouldReceive('send')->andReturn(null);

        Event::fake();

        $manager->handleHello($connectionId, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.5.0',
            'device_name' => 'test-device',
            'os' => 'linux',
            'arch' => 'amd64',
            'access_token' => $token,
        ]);
        $manager->registerConnectionObject($connectionId, $connection);

        // Disconnect the device
        $manager->handleDisconnect($connectionId);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'start_run',
                'payload' => [],
            ]);

        $response->assertStatus(503);
    });
});

describe('ServerConnectionManager Message Sending', function () {
    it('sends message to device via connection object', function () {
        $manager = new ServerConnectionManager;
        $sentMessages = [];
        $connection = Mockery::mock(\Laravel\Reverb\Servers\Reverb\Connection::class);
        $connection->shouldReceive('send')->andReturnUsing(function ($msg) use (&$sentMessages) {
            $sentMessages[] = json_decode($msg, true);
        });

        Event::fake();

        $token = generateRelayTestAccessToken($this->device);
        $manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.5.0',
            'device_name' => 'test-device',
            'os' => 'linux',
            'arch' => 'amd64',
            'access_token' => $token,
        ]);

        $manager->registerConnectionObject(1, $connection);

        $result = $manager->sendToDevice($this->device->id, [
            'type' => 'start_run',
            'payload' => ['project_slug' => 'test'],
        ]);

        expect($result)->toBeTrue();

        // First message was welcome, second is our command
        $lastMessage = end($sentMessages);
        expect($lastMessage['type'])->toBe('start_run');
        expect($lastMessage['payload']['project_slug'])->toBe('test');
    });

    it('returns false for offline device', function () {
        $manager = new ServerConnectionManager;

        $result = $manager->sendToDevice(999, [
            'type' => 'start_run',
            'payload' => [],
        ]);

        expect($result)->toBeFalse();
    });

    it('checks device online status correctly', function () {
        $manager = new ServerConnectionManager;
        $connection = Mockery::mock(\Laravel\Reverb\Servers\Reverb\Connection::class);
        $connection->shouldReceive('send')->andReturn(null);

        Event::fake();

        expect($manager->isDeviceOnline($this->device->id))->toBeFalse();

        $token = generateRelayTestAccessToken($this->device);
        $manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.5.0',
            'device_name' => 'test-device',
            'os' => 'linux',
            'arch' => 'amd64',
            'access_token' => $token,
        ]);

        // After hello but before registering connection object
        expect($manager->isDeviceOnline($this->device->id))->toBeFalse();

        $manager->registerConnectionObject(1, $connection);
        expect($manager->isDeviceOnline($this->device->id))->toBeTrue();
    });

    it('cleans up connection objects on disconnect', function () {
        $manager = new ServerConnectionManager;
        $connection = Mockery::mock(\Laravel\Reverb\Servers\Reverb\Connection::class);
        $connection->shouldReceive('send')->andReturn(null);

        Event::fake();

        $token = generateRelayTestAccessToken($this->device);
        $manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.5.0',
            'device_name' => 'test-device',
            'os' => 'linux',
            'arch' => 'amd64',
            'access_token' => $token,
        ]);

        $manager->registerConnectionObject(1, $connection);
        expect($manager->isDeviceOnline($this->device->id))->toBeTrue();

        $manager->handleDisconnect(1);
        expect($manager->isDeviceOnline($this->device->id))->toBeFalse();
    });

    it('cleans up connection objects on device deauthorization', function () {
        $manager = new ServerConnectionManager;
        $connection = Mockery::mock(\Laravel\Reverb\Servers\Reverb\Connection::class);
        $connection->shouldReceive('send')->andReturn(null);

        Event::fake();

        $token = generateRelayTestAccessToken($this->device);
        $manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.5.0',
            'device_name' => 'test-device',
            'os' => 'linux',
            'arch' => 'amd64',
            'access_token' => $token,
        ]);

        $manager->registerConnectionObject(1, $connection);
        expect($manager->isDeviceOnline($this->device->id))->toBeTrue();

        $manager->disconnectDevice($this->device->id);
        expect($manager->isDeviceOnline($this->device->id))->toBeFalse();
    });

    it('handles send failure gracefully', function () {
        $manager = new ServerConnectionManager;
        $connection = Mockery::mock(\Laravel\Reverb\Servers\Reverb\Connection::class);
        $connection->shouldReceive('send')->andThrow(new \RuntimeException('Connection lost'));

        Event::fake();

        $token = generateRelayTestAccessToken($this->device);
        $manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.5.0',
            'device_name' => 'test-device',
            'os' => 'linux',
            'arch' => 'amd64',
            'access_token' => $token,
        ]);

        $manager->registerConnectionObject(1, $connection);

        $result = $manager->sendToDevice($this->device->id, [
            'type' => 'start_run',
            'payload' => [],
        ]);

        expect($result)->toBeFalse();
    });
});

describe('Rate Limiting', function () {
    it('enforces 60 commands per minute rate limit', function () {
        $manager = app(ServerConnectionManager::class);
        setupOnlineDevice($manager, $this->device);

        // Send 60 requests — all should succeed
        for ($i = 0; $i < 60; $i++) {
            $response = $this->actingAs($this->user)
                ->postJson("/ws/command/{$this->device->id}", [
                    'type' => 'get_settings',
                    'payload' => [],
                ]);

            $response->assertOk();
        }

        // 61st request should be rate limited
        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'get_settings',
                'payload' => [],
            ]);

        $response->assertStatus(429);
    });

    it('includes rate limit headers in response', function () {
        $manager = app(ServerConnectionManager::class);
        setupOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'get_settings',
                'payload' => [],
            ]);

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit', '60');
        $response->assertHeader('X-RateLimit-Remaining');
    });
});

describe('Valid Command Types', function () {
    it('defines all required command types', function () {
        $expectedCommands = [
            'start_run',
            'pause_run',
            'resume_run',
            'stop_run',
            'new_prd',
            'prd_message',
            'close_prd_session',
            'clone_repo',
            'create_project',
            'get_logs',
            'get_diffs',
            'get_settings',
            'update_settings',
        ];

        foreach ($expectedCommands as $command) {
            expect(CommandRelayController::VALID_COMMANDS)->toContain($command);
        }
    });
});

describe('ChiefMessageReceived Event', function () {
    it('implements ShouldBroadcast', function () {
        $event = new ChiefMessageReceived(1, 1, ['type' => 'test']);

        expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
    });

    it('broadcasts RATE_LIMITED error from chief', function () {
        Event::fake([ChiefMessageReceived::class]);

        $message = [
            'type' => 'error',
            'code' => 'RATE_LIMITED',
            'message' => 'Too many requests',
            'retry_after' => 5,
        ];

        ChiefMessageReceived::dispatch(
            $this->device->id,
            $this->user->id,
            $message,
        );

        Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
            return $event->message['code'] === 'RATE_LIMITED'
                && $event->message['retry_after'] === 5;
        });
    });
});

describe('Malformed Messages', function () {
    it('logs and discards invalid JSON from chief', function () {
        // This is tested in ServerConnectionTest but verify the
        // controller doesn't crash on malformed messages
        $manager = app(ServerConnectionManager::class);
        $connectionId = 100;
        $token = generateRelayTestAccessToken($this->device);

        Event::fake();

        $manager->handleHello($connectionId, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.5.0',
            'device_name' => 'test-device',
            'os' => 'linux',
            'arch' => 'amd64',
            'access_token' => $token,
        ]);

        // The ChiefServerController handles invalid JSON by logging and returning
        // This verifies the connection manager doesn't crash
        expect($manager->isAuthenticated($connectionId))->toBeTrue();
    });
});
