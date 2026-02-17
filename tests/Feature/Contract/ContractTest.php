<?php

use App\Events\ChiefCommandDispatched;
use App\Events\ChiefMessageReceived;
use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'contract-test-device',
    ]);
    $this->fixturesDir = base_path('contract/fixtures');
});

function generateContractToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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

function loadFixture(string $relativePath): array
{
    $path = base_path("contract/fixtures/{$relativePath}");
    $json = file_get_contents($path);
    expect($json)->not->toBeFalse("Fixture not found: {$path}");

    return json_decode($json, true);
}

/*
|--------------------------------------------------------------------------
| server-to-cli: welcome_response
|--------------------------------------------------------------------------
*/

test('connect response matches welcome_response fixture structure', function () {
    Event::fake();

    $token = generateContractToken($this->device);

    $fixture = loadFixture('server-to-cli/welcome_response.json');

    $response = $this->postJson('/api/device/connect', [
        'chief_version' => '1.0.0',
        'device_name' => 'test-device',
        'os' => 'darwin',
        'arch' => 'arm64',
        'protocol_version' => 1,
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk();
    $data = $response->json();

    // Verify all keys from fixture exist in response
    expect($data)->toHaveKey('type')
        ->toHaveKey('protocol_version')
        ->toHaveKey('device_id')
        ->toHaveKey('session_id')
        ->toHaveKey('reverb');

    // Type must match
    expect($data['type'])->toBe($fixture['type']);
    expect($data['protocol_version'])->toBe($fixture['protocol_version']);

    // Reverb config structure
    expect($data['reverb'])->toHaveKey('key')
        ->toHaveKey('host')
        ->toHaveKey('port')
        ->toHaveKey('scheme');
});

test('connect response reverb.port is integer not string', function () {
    Event::fake();

    $token = generateContractToken($this->device);

    $response = $this->postJson('/api/device/connect', [
        'chief_version' => '1.0.0',
        'device_name' => 'test-device',
        'os' => 'darwin',
        'arch' => 'arm64',
        'protocol_version' => 1,
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk();
    $data = $response->json();

    // Regression: PHP env() returns strings. Port MUST be an integer.
    expect($data['reverb']['port'])->toBeInt();
});

/*
|--------------------------------------------------------------------------
| cli-to-server: state_snapshot via messages endpoint
|--------------------------------------------------------------------------
*/

test('messages endpoint accepts state_snapshot fixture without error', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateContractToken($this->device);
    $fixture = loadFixture('cli-to-server/state_snapshot.json');

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => \Illuminate\Support\Str::uuid()->toString(),
        'messages' => [$fixture],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJson(['accepted' => 1]);
});

test('state_snapshot fixture directly populates CachedProjectState', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateContractToken($this->device);
    $fixture = loadFixture('cli-to-server/state_snapshot.json');

    // Send the state_snapshot fixture as-is — the ingestion endpoint must
    // handle it directly (not wrapped in project_state)
    $this->postJson('/api/device/messages', [
        'batch_id' => \Illuminate\Support\Str::uuid()->toString(),
        'messages' => [$fixture],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    // The fixture uses "name" not "project_slug" and has projects at top level
    $project = $fixture['projects'][0];
    expect($project)->toHaveKey('name');
    expect($project)->not->toHaveKey('project_slug');

    $cached = CachedProjectState::where('device_authorization_id', $this->device->id)
        ->where('project_slug', $project['name'])
        ->first();

    expect($cached)->not->toBeNull()
        ->and($cached->project_name)->toBe($project['name'])
        ->and($cached->git_branch)->toBe($project['branch'])
        ->and($cached->last_commit_hash)->toBe($project['commit']['hash'])
        ->and($cached->last_commit_message)->toBe($project['commit']['message']);
});

/*
|--------------------------------------------------------------------------
| cli-to-server: messages_batch
|--------------------------------------------------------------------------
*/

test('messages_batch fixture is accepted by messages endpoint', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateContractToken($this->device);
    $fixture = loadFixture('cli-to-server/messages_batch.json');

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => $fixture['batch_id'],
        'messages' => $fixture['messages'],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJson(['accepted' => count($fixture['messages'])]);
});

/*
|--------------------------------------------------------------------------
| server-to-cli: command fixtures match CommandRelayController output
|--------------------------------------------------------------------------
*/

test('command_create_project fixture matches relay format', function () {
    $fixture = loadFixture('server-to-cli/command_create_project.json');

    // Commands must have type + payload wrapper
    expect($fixture)->toHaveKey('type')
        ->toHaveKey('payload');
    expect($fixture['type'])->toBe('create_project');
    expect($fixture['payload'])->toHaveKey('name');
    expect($fixture['payload']['name'])->toBe('new-project');
    expect($fixture['payload'])->toHaveKey('git_init');
    expect($fixture['payload']['git_init'])->toBeTrue();
});

test('command_start_run fixture matches relay format', function () {
    $fixture = loadFixture('server-to-cli/command_start_run.json');

    expect($fixture)->toHaveKey('type')
        ->toHaveKey('payload');
    expect($fixture['type'])->toBe('start_run');
    expect($fixture['payload'])->toHaveKey('project');
    expect($fixture['payload'])->toHaveKey('prd_id');
});

test('command_list_projects fixture matches relay format', function () {
    $fixture = loadFixture('server-to-cli/command_list_projects.json');

    expect($fixture)->toHaveKey('type')
        ->toHaveKey('payload');
    expect($fixture['type'])->toBe('list_projects');
});

test('CommandRelayController dispatches in fixture format', function () {
    Event::fake([ChiefCommandDispatched::class]);

    $this->device->update(['is_online' => true]);

    $this->actingAs($this->user)
        ->postJson("/ws/command/{$this->device->id}", [
            'type' => 'create_project',
            'payload' => [
                'name' => 'new-project',
                'git_init' => true,
            ],
        ])
        ->assertOk();

    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
        // The broadcast payload should match the fixture format: {"type": ..., "payload": {...}}
        $command = $event->command;
        expect($command)->toHaveKey('type')
            ->toHaveKey('payload');
        expect($command['type'])->toBe('create_project');
        expect($command['payload'])->toHaveKey('name');
        expect($command['payload']['name'])->toBe('new-project');

        return true;
    });
});

/*
|--------------------------------------------------------------------------
| server-to-cli: command_get_prds, command_get_settings, command_get_diffs
|--------------------------------------------------------------------------
*/

test('command_get_prds fixture matches relay format', function () {
    $fixture = loadFixture('server-to-cli/command_get_prds.json');

    expect($fixture)->toHaveKey('type')
        ->toHaveKey('payload');
    expect($fixture['type'])->toBe('get_prds');
    expect($fixture['payload'])->toHaveKey('project');
    expect($fixture['payload']['project'])->toBe('my-project');
});

test('command_get_settings fixture matches relay format', function () {
    $fixture = loadFixture('server-to-cli/command_get_settings.json');

    expect($fixture)->toHaveKey('type')
        ->toHaveKey('payload');
    expect($fixture['type'])->toBe('get_settings');
    expect($fixture['payload'])->toHaveKey('project');
    expect($fixture['payload']['project'])->toBe('my-project');
});

test('command_get_diffs fixture matches relay format', function () {
    $fixture = loadFixture('server-to-cli/command_get_diffs.json');

    expect($fixture)->toHaveKey('type')
        ->toHaveKey('payload');
    expect($fixture['type'])->toBe('get_diffs');
    expect($fixture['payload'])->toHaveKey('project');
    expect($fixture['payload'])->toHaveKey('story_id');
});

/*
|--------------------------------------------------------------------------
| cli-to-server: response fixtures accepted by messages endpoint
|--------------------------------------------------------------------------
*/

test('prds_response fixture is accepted by messages endpoint', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateContractToken($this->device);
    $fixture = loadFixture('cli-to-server/prds_response.json');

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => \Illuminate\Support\Str::uuid()->toString(),
        'messages' => [$fixture],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJson(['accepted' => 1]);

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        expect($event->message['type'])->toBe('prds_response');
        expect($event->message['payload']['project'])->toBe('my-project');
        expect($event->message['payload']['prds'])->toBeArray()->toHaveCount(2);

        return true;
    });
});

test('settings_response fixture is accepted by messages endpoint', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateContractToken($this->device);
    $fixture = loadFixture('cli-to-server/settings_response.json');

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => \Illuminate\Support\Str::uuid()->toString(),
        'messages' => [$fixture],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJson(['accepted' => 1]);

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        expect($event->message['type'])->toBe('settings_response');
        expect($event->message['payload']['project'])->toBe('my-project');
        expect($event->message['payload']['settings'])->toBeArray();
        expect($event->message['payload']['settings']['max_iterations'])->toBe(5);

        return true;
    });
});

test('diffs_response fixture is accepted by messages endpoint', function () {
    Event::fake([ChiefMessageReceived::class]);

    $token = generateContractToken($this->device);
    $fixture = loadFixture('cli-to-server/diffs_response.json');

    $response = $this->postJson('/api/device/messages', [
        'batch_id' => \Illuminate\Support\Str::uuid()->toString(),
        'messages' => [$fixture],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJson(['accepted' => 1]);

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        expect($event->message['type'])->toBe('diffs_response');
        expect($event->message['payload']['project'])->toBe('my-project');
        expect($event->message['payload']['story_id'])->toBe('US-001');
        expect($event->message['payload']['files'])->toBeArray()->toHaveCount(1);

        return true;
    });
});
