<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\ServerConnectionManager;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'test-device',
    ]);
});

function generateProjectTestAccessToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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

function setupProjectOnlineDevice(ServerConnectionManager $manager, DeviceAuthorization $device): void
{
    $connectionId = $device->id * 1000;
    $token = generateProjectTestAccessToken($device);

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

/*
|--------------------------------------------------------------------------
| Clone Repository via WebSocket Relay
|--------------------------------------------------------------------------
*/

describe('Clone Repository', function () {
    it('sends clone_repo command to chief server', function () {
        $manager = app(ServerConnectionManager::class);
        setupProjectOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'clone_repo',
                'payload' => [
                    'url' => 'https://github.com/user/repo.git',
                    'directory' => 'my-repo',
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'sent',
                'type' => 'clone_repo',
                'device_id' => $this->device->id,
            ]);
    });

    it('cannot clone when server is offline', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'clone_repo',
                'payload' => [
                    'url' => 'https://github.com/user/repo.git',
                    'directory' => 'my-repo',
                ],
            ]);

        $response->assertStatus(503)
            ->assertJson([
                'error' => 'server_offline',
            ]);
    });

    it('rate limits clone_repo to 10 per hour', function () {
        $manager = app(ServerConnectionManager::class);
        setupProjectOnlineDevice($manager, $this->device);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->user)
                ->postJson("/ws/command/{$this->device->id}", [
                    'type' => 'clone_repo',
                    'payload' => [
                        'url' => "https://github.com/user/repo-{$i}.git",
                        'directory' => "repo-{$i}",
                    ],
                ])->assertOk();
        }

        // 11th should be rate limited
        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'clone_repo',
                'payload' => [
                    'url' => 'https://github.com/user/repo-overflow.git',
                    'directory' => 'repo-overflow',
                ],
            ]);

        $response->assertStatus(429)
            ->assertJson([
                'error' => 'rate_limited',
            ]);
        $response->assertHeader('Retry-After');
    });
});

/*
|--------------------------------------------------------------------------
| Create Project via WebSocket Relay
|--------------------------------------------------------------------------
*/

describe('Create Project', function () {
    it('sends create_project command to chief server', function () {
        $manager = app(ServerConnectionManager::class);
        setupProjectOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'create_project',
                'payload' => [
                    'name' => 'my-new-project',
                    'git_init' => true,
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'sent',
                'type' => 'create_project',
            ]);
    });

    it('cannot create project when server is offline', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'create_project',
                'payload' => [
                    'name' => 'offline-project',
                    'git_init' => true,
                ],
            ]);

        $response->assertStatus(503);
    });

    it('shares the clone_repo rate limit for create_project', function () {
        $manager = app(ServerConnectionManager::class);
        setupProjectOnlineDevice($manager, $this->device);

        // Use up 9 of the 10 allowed with clone_repo
        for ($i = 0; $i < 9; $i++) {
            $this->actingAs($this->user)
                ->postJson("/ws/command/{$this->device->id}", [
                    'type' => 'clone_repo',
                    'payload' => [
                        'url' => "https://github.com/user/repo-{$i}.git",
                        'directory' => "repo-{$i}",
                    ],
                ])->assertOk();
        }

        // 10th with create_project should still succeed
        $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'create_project',
                'payload' => ['name' => 'new-proj'],
            ])->assertOk();

        // 11th should be rate limited
        $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'create_project',
                'payload' => ['name' => 'overflow-proj'],
            ])->assertStatus(429);
    });
});

/*
|--------------------------------------------------------------------------
| Get Diffs via WebSocket Relay
|--------------------------------------------------------------------------
*/

describe('Get Diffs', function () {
    it('sends get_diffs command to chief server', function () {
        $manager = app(ServerConnectionManager::class);
        setupProjectOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'get_diffs',
                'payload' => [
                    'project_slug' => 'my-project',
                    'story_id' => 'US-001',
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'sent',
                'type' => 'get_diffs',
            ]);
    });
});

/*
|--------------------------------------------------------------------------
| Get/Update Settings via WebSocket Relay
|--------------------------------------------------------------------------
*/

describe('Project Settings via WebSocket', function () {
    it('sends get_settings command to chief server', function () {
        $manager = app(ServerConnectionManager::class);
        setupProjectOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'get_settings',
                'payload' => ['project_slug' => 'my-project'],
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'sent', 'type' => 'get_settings']);
    });

    it('sends update_settings command to chief server', function () {
        $manager = app(ServerConnectionManager::class);
        setupProjectOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'update_settings',
                'payload' => [
                    'project_slug' => 'my-project',
                    'max_iterations' => 10,
                    'auto_commit' => true,
                    'commit_prefix' => 'feat',
                    'claude_model' => 'claude-sonnet-4-5-20250929',
                ],
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'sent', 'type' => 'update_settings']);
    });
});

/*
|--------------------------------------------------------------------------
| Project Detail Pages
|--------------------------------------------------------------------------
*/

describe('Project Detail Pages', function () {
    it('renders overview tab', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'detail-project',
            'project_name' => 'Detail Project',
            'current_prd_name' => 'Main PRD',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/detail-project');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Overview')
            ->where('projectSlug', 'detail-project')
            ->where('projectName', 'Detail Project')
        );
    });

    it('renders diffs tab', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'diffs-project',
            'project_name' => 'Diffs Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/diffs-project/diffs');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Diffs')
            ->where('projectSlug', 'diffs-project')
            ->has('deviceId')
        );
    });

    it('renders PRDs tab', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'prds-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/prds-project/prds');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Prds')
            ->where('projectSlug', 'prds-project')
            ->has('deviceId')
        );
    });

    it('renders settings tab', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'settings-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/settings-project/settings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Settings')
            ->where('projectSlug', 'settings-project')
            ->has('deviceId')
        );
    });

    it('renders PRD create page', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'prd-create-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/prd-create-project/prd/new');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/PrdChat')
            ->where('mode', 'create')
            ->has('deviceId')
        );
    });

    it('renders PRD refine page', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'prd-refine-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/prd-refine-project/prd/main/refine');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/PrdChat')
            ->where('mode', 'refine')
            ->where('prdId', 'main')
            ->has('deviceId')
        );
    });

    it('returns 404 for non-existent project', function () {
        $response = $this->actingAs($this->user)->get('/projects/nonexistent-project');

        $response->assertStatus(404);
    });

    it('returns 404 for another users project', function () {
        $otherUser = User::factory()->create();
        $otherDevice = DeviceAuthorization::factory()->for($otherUser)->online()->create();
        CachedProjectState::factory()->for($otherDevice)->idle()->create([
            'project_slug' => 'other-user-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/other-user-project');

        $response->assertStatus(404);
    });

    it('indicates hasActiveRun on PRD create page for running project', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'active-run-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/active-run-project/prd/new');

        $response->assertInertia(fn ($page) => $page
            ->where('hasActiveRun', true)
        );
    });

    it('indicates no active run on PRD create page for idle project', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'idle-prd-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/idle-prd-project/prd/new');

        $response->assertInertia(fn ($page) => $page
            ->where('hasActiveRun', false)
        );
    });
});
