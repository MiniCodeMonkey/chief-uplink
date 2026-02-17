<?php

use App\Events\ChiefMessageReceived;
use App\Http\Controllers\Api\MessageIngestionController;
use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\WebSocketMessageBuffer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'test-device',
    ]);
});

function generateIngestionToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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
| Successful batch ingestion
|--------------------------------------------------------------------------
*/

test('successful batch ingestion returns accepted count and batch info', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);
    $batchId = Str::uuid()->toString();

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => $batchId,
        'messages' => [
            ['type' => 'claude_output', 'id' => 'msg-1', 'payload' => ['text' => 'Hello']],
            ['type' => 'run_progress', 'id' => 'msg-2', 'payload' => ['progress' => 50]],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJson([
            'accepted' => 2,
            'batch_id' => $batchId,
            'session_id' => $this->device->session_id,
        ]);
});

test('batch ingestion broadcasts each message to browser channel', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);
    $batchId = Str::uuid()->toString();

    $this->postJson('/api/device/messages', [
        'batch_id' => $batchId,
        'messages' => [
            ['type' => 'claude_output', 'id' => 'msg-1', 'payload' => ['text' => 'Hello']],
            ['type' => 'run_progress', 'id' => 'msg-2', 'payload' => ['progress' => 50]],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    Event::assertDispatchedTimes(ChiefMessageReceived::class, 2);

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->deviceId === $this->device->id
            && $event->userId === $this->user->id
            && $event->message['type'] === 'claude_output';
    });

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->message['type'] === 'run_progress';
    });
});

test('batch ingestion buffers bufferable messages', function () {
    Event::fake([ChiefMessageReceived::class]);

    $mock = $this->mock(WebSocketMessageBuffer::class);
    $mock->shouldReceive('buffer')
        ->once()
        ->with($this->device->id, $this->device->session_id, \Mockery::on(function ($msg) {
            return $msg['type'] === 'claude_output';
        }))
        ->andReturn(true);

    // log_lines is not in BUFFERABLE_TYPES, so buffer should only be called once
    $mock->shouldReceive('buffer')
        ->once()
        ->with($this->device->id, $this->device->session_id, \Mockery::on(function ($msg) {
            return $msg['type'] === 'log_lines';
        }))
        ->andReturn(false);

    $token = generateIngestionToken($this->device);
    $batchId = Str::uuid()->toString();

    $this->postJson('/api/device/messages', [
        'batch_id' => $batchId,
        'messages' => [
            ['type' => 'claude_output', 'id' => 'msg-1', 'payload' => ['text' => 'Hello']],
            ['type' => 'log_lines', 'id' => 'msg-2', 'payload' => ['lines' => ['line1']]],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);
});

test('project_state message updates cached project state', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);
    $batchId = Str::uuid()->toString();

    $this->postJson('/api/device/messages', [
        'batch_id' => $batchId,
        'messages' => [
            [
                'type' => 'project_state',
                'payload' => [
                    'projects' => [
                        [
                            'project_slug' => 'my-project',
                            'project_name' => 'My Project',
                            'status' => 'running',
                            'git_branch' => 'main',
                            'stories_completed' => 3,
                            'stories_total' => 10,
                        ],
                    ],
                ],
            ],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $cached = CachedProjectState::where('device_authorization_id', $this->device->id)
        ->where('project_slug', 'my-project')
        ->first();

    expect($cached)->not->toBeNull()
        ->and($cached->project_name)->toBe('My Project')
        ->and($cached->status)->toBe('running')
        ->and($cached->git_branch)->toBe('main')
        ->and($cached->stories_completed)->toBe(3)
        ->and($cached->stories_total)->toBe(10);
});

test('project_state message accepts name field instead of project_slug', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);
    $batchId = Str::uuid()->toString();

    $this->postJson('/api/device/messages', [
        'batch_id' => $batchId,
        'messages' => [
            [
                'type' => 'project_state',
                'payload' => [
                    'projects' => [
                        [
                            'name' => 'my-cli-project',
                            'status' => 'idle',
                            'git_branch' => 'main',
                        ],
                    ],
                ],
            ],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $cached = CachedProjectState::where('device_authorization_id', $this->device->id)
        ->where('project_slug', 'my-cli-project')
        ->first();

    expect($cached)->not->toBeNull()
        ->and($cached->project_name)->toBe('my-cli-project')
        ->and($cached->git_branch)->toBe('main');
});

test('state_snapshot message updates cached project state with CLI field names', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);
    $batchId = Str::uuid()->toString();

    // This is the exact format the CLI sends — projects at top level,
    // using "name", "branch", "commit.hash" instead of "project_slug", "git_branch", etc.
    $this->postJson('/api/device/messages', [
        'batch_id' => $batchId,
        'messages' => [
            [
                'type' => 'state_snapshot',
                'id' => 'msg-1',
                'timestamp' => now()->toISOString(),
                'projects' => [
                    [
                        'name' => 'my-project',
                        'path' => '/home/user/projects/my-project',
                        'has_chief' => true,
                        'branch' => 'develop',
                        'commit' => [
                            'hash' => 'abc1234',
                            'message' => 'feat: add login',
                            'author' => 'dev',
                            'timestamp' => now()->toISOString(),
                        ],
                        'prds' => [],
                    ],
                ],
                'runs' => [],
                'sessions' => [],
            ],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $cached = CachedProjectState::where('device_authorization_id', $this->device->id)
        ->where('project_slug', 'my-project')
        ->first();

    expect($cached)->not->toBeNull()
        ->and($cached->project_name)->toBe('my-project')
        ->and($cached->git_branch)->toBe('develop')
        ->and($cached->last_commit_hash)->toBe('abc1234')
        ->and($cached->last_commit_message)->toBe('feat: add login');
});

/*
|--------------------------------------------------------------------------
| Implicit heartbeat
|--------------------------------------------------------------------------
*/

test('message ingestion updates last_heartbeat_at as implicit heartbeat', function () {
    Event::fake([ChiefMessageReceived::class]);

    // Set heartbeat to a known past time
    $pastTime = now()->subMinutes(5);
    $this->device->update(['last_heartbeat_at' => $pastTime]);

    $token = generateIngestionToken($this->device);
    $batchId = Str::uuid()->toString();

    $this->postJson('/api/device/messages', [
        'batch_id' => $batchId,
        'messages' => [
            ['type' => 'claude_output', 'id' => 'msg-1', 'payload' => ['text' => 'Hello']],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $this->device->refresh();
    expect($this->device->last_heartbeat_at)->toBeGreaterThan($pastTime);
});

/*
|--------------------------------------------------------------------------
| Idempotent retry (batch deduplication)
|--------------------------------------------------------------------------
*/

test('duplicate batch_id returns cached response without reprocessing', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);
    $batchId = Str::uuid()->toString();

    $payload = [
        'batch_id' => $batchId,
        'messages' => [
            ['type' => 'claude_output', 'id' => 'msg-1', 'payload' => ['text' => 'Hello']],
        ],
    ];

    // First request
    $response1 = $this->postJson('/api/device/messages', $payload, [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response1->assertOk()->assertJson(['accepted' => 1]);

    // Event dispatched once
    Event::assertDispatchedTimes(ChiefMessageReceived::class, 1);

    // Second request with same batch_id — should return cached response
    $response2 = $this->postJson('/api/device/messages', $payload, [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response2->assertOk()->assertJson([
        'accepted' => 1,
        'batch_id' => $batchId,
    ]);

    // Event should NOT be dispatched again (still 1 total)
    Event::assertDispatchedTimes(ChiefMessageReceived::class, 1);
});

/*
|--------------------------------------------------------------------------
| Validation errors
|--------------------------------------------------------------------------
*/

test('empty messages array is rejected', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(422);
});

test('missing messages field is rejected', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(422);
});

test('missing batch_id is rejected', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);

    $response = $this->postJson('/api/device/messages', [
        'messages' => [
            ['type' => 'claude_output'],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(422);
});

test('oversized batch with more than 50 messages is rejected', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);

    $messages = array_fill(0, 51, ['type' => 'claude_output', 'id' => 'msg']);

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => $messages,
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(422);
});

test('unknown message type rejects the entire batch', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            ['type' => 'claude_output', 'id' => 'msg-1'],
            ['type' => 'totally_invalid_type', 'id' => 'msg-2'],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'unknown_message_type',
        ]);

    // No events should be dispatched since the batch was rejected
    Event::assertNotDispatched(ChiefMessageReceived::class);
});

test('message without type field is rejected by validation', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            ['id' => 'msg-1', 'payload' => ['text' => 'no type']],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Authentication failures
|--------------------------------------------------------------------------
*/

test('message ingestion with revoked device returns 401', function () {
    $this->device->update(['revoked_at' => now()]);
    $token = generateIngestionToken($this->device);

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            ['type' => 'claude_output'],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'revoked_device']);
});

test('message ingestion without token returns 401', function () {
    $response = $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            ['type' => 'claude_output'],
        ],
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'missing_token']);
});

/*
|--------------------------------------------------------------------------
| All allowed message types are accepted
|--------------------------------------------------------------------------
*/

test('ALLOWED_TYPES includes all response types the frontend expects', function () {
    // Contract test: the frontend listens for these message types via useChiefMessages.on().
    // If chief sends a response type that isn't in ALLOWED_TYPES, the message ingestion
    // endpoint rejects it with 422 and the browser never receives the response.
    $expectedResponseTypes = [
        // Run lifecycle
        'run_progress',
        'run_complete',
        'run_paused',

        // Streaming output
        'claude_output',
        'prd_output',
        'prd_response_complete',

        // Command responses (chief responds to get_* commands)
        'prds_response',
        'diffs_response',
        'settings_response',
        'settings_updated',
        'log_lines',

        // Session lifecycle
        'session_expired',
        'session_timeout_warning',

        // Errors
        'error',
    ];

    foreach ($expectedResponseTypes as $type) {
        expect(in_array($type, MessageIngestionController::ALLOWED_TYPES, true))
            ->toBeTrue("Frontend expects '{$type}' but it's missing from ALLOWED_TYPES");
    }
});

test('all allowed message types are accepted', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);

    $messages = collect(MessageIngestionController::ALLOWED_TYPES)
        ->map(fn ($type) => ['type' => $type, 'id' => Str::uuid()->toString()])
        ->toArray();

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => $messages,
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJson([
            'accepted' => count(MessageIngestionController::ALLOWED_TYPES),
        ]);
});

/*
|--------------------------------------------------------------------------
| End-to-end: chief response ingestion and broadcast
|--------------------------------------------------------------------------
| These tests simulate the chief server responding to get_* commands:
| chief sends a response message → ingestion endpoint accepts it → broadcast.
| This catches the exact bug where a response type is missing from ALLOWED_TYPES,
| since the ingestion endpoint rejects unknown types with 422.
*/

test('prds_response from chief is accepted and broadcast to browser', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);

    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            [
                'type' => 'prds_response',
                'payload' => [
                    'project_slug' => 'my-project',
                    'prds' => [
                        ['id' => 'prd-1', 'name' => 'Auth Feature', 'story_count' => 5, 'status' => 'active'],
                    ],
                ],
            ],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->deviceId === $this->device->id
            && $event->message['type'] === 'prds_response';
    });
});

test('diffs_response from chief is accepted and broadcast to browser', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);

    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            [
                'type' => 'diffs_response',
                'payload' => ['project_slug' => 'my-project', 'diffs' => []],
            ],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->message['type'] === 'diffs_response';
    });
});

test('settings_response from chief is accepted and broadcast to browser', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateIngestionToken($this->device);

    $this->postJson('/api/device/messages', [
        'batch_id' => Str::uuid()->toString(),
        'messages' => [
            [
                'type' => 'settings_response',
                'payload' => ['project_slug' => 'my-project', 'settings' => []],
            ],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->message['type'] === 'settings_response';
    });
});
