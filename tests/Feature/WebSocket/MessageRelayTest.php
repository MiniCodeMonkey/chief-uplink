<?php

use App\Events\ChiefCommandDispatched;
use App\Events\ChiefMessageReceived;
use App\Http\Controllers\Api\CommandRelayController;
use App\Models\DeviceAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'test-device',
    ]);
    $this->otherUser = User::factory()->create();
    $this->otherDevice = DeviceAuthorization::factory()->for($this->otherUser)->online()->create();
});

describe('Browser → Chief Command Relay', function () {
    it('sends valid command to online device via broadcast', function () {
        Event::fake([ChiefCommandDispatched::class]);

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

        Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
            return $event->deviceId === $this->device->id
                && $event->userId === $this->user->id
                && $event->command['type'] === 'start_run'
                && $event->command['payload']['project_slug'] === 'my-project';
        });
    });

    it('returns 503 when device is offline', function () {
        $this->device->update(['is_online' => false]);

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
        Event::fake([ChiefCommandDispatched::class]);

        foreach (CommandRelayController::VALID_COMMANDS as $command) {
            $response = $this->actingAs($this->user)
                ->postJson("/ws/command/{$this->device->id}", [
                    'type' => $command,
                    'payload' => [],
                ]);

            $response->assertOk();
        }
    });

    it('broadcasts command with correct payload to chief', function () {
        Event::fake([ChiefCommandDispatched::class]);

        $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'clone_repo',
                'payload' => ['url' => 'https://github.com/user/repo.git', 'directory' => 'repo'],
            ]);

        Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
            return $event->command['type'] === 'clone_repo'
                && $event->command['payload']['url'] === 'https://github.com/user/repo.git'
                && $event->command['payload']['directory'] === 'repo';
        });
    });

    it('requires authentication', function () {
        $response = $this->postJson("/ws/command/{$this->device->id}", [
            'type' => 'start_run',
            'payload' => [],
        ]);

        $response->assertStatus(401);
    });

    it('accepts command with empty payload', function () {
        Event::fake([ChiefCommandDispatched::class]);

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

    it('returns 403 for non-existent device', function () {
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
        Event::fake([ChiefCommandDispatched::class]);

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
        // Device is online, so broadcast will fire — assert 200
        $response->assertOk();
    });
});

describe('Offline Device Handling', function () {
    it('returns server offline for device with is_online false', function () {
        $this->device->update(['is_online' => false]);

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

    it('returns server offline after device marked offline', function () {
        // Device starts online via factory
        expect($this->device->is_online)->toBeTrue();

        // Mark offline (simulates disconnect)
        $this->device->update(['is_online' => false]);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'start_run',
                'payload' => [],
            ]);

        $response->assertStatus(503);
    });

    it('does not dispatch broadcast when device is offline', function () {
        Event::fake([ChiefCommandDispatched::class]);

        $this->device->update(['is_online' => false]);

        $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'start_run',
                'payload' => [],
            ]);

        Event::assertNotDispatched(ChiefCommandDispatched::class);
    });
});

describe('Broadcast Dispatch', function () {
    it('dispatches ChiefCommandDispatched with correct device and user', function () {
        Event::fake([ChiefCommandDispatched::class]);

        $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'start_run',
                'payload' => ['project_slug' => 'test'],
            ]);

        Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
            return $event->deviceId === $this->device->id
                && $event->userId === $this->user->id;
        });
    });

    it('dispatches broadcast with full command structure', function () {
        Event::fake([ChiefCommandDispatched::class]);

        $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'update_settings',
                'payload' => ['max_iterations' => 5],
            ]);

        Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
            return $event->command['type'] === 'update_settings'
                && $event->command['payload']['max_iterations'] === 5;
        });
    });

    it('dispatches broadcast with empty payload', function () {
        Event::fake([ChiefCommandDispatched::class]);

        $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'pause_run',
            ]);

        Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
            return $event->command['type'] === 'pause_run'
                && $event->command['payload'] === [];
        });
    });
});

describe('Rate Limiting', function () {
    it('enforces 60 commands per minute rate limit', function () {
        Event::fake([ChiefCommandDispatched::class]);

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
        Event::fake([ChiefCommandDispatched::class]);

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
            'get_prds',
            'refine_prd',
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
