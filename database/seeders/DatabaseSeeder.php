<?php

namespace Database\Seeders;

use App\Models\CachedProjectState;
use App\Models\CloudDeployment;
use App\Models\DeviceAuthorization;
use App\Models\LogCache;
use App\Models\RunHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create 3 users with GitHub-style usernames/avatars
        $users = [
            User::factory()->create([
                'name' => 'Sarah Chen',
                'email' => 'sarah@example.com',
                'github_username' => 'sarahchen',
                'github_id' => '1234567',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/1234567',
            ]),
            User::factory()->create([
                'name' => 'Marcus Webb',
                'email' => 'marcus@example.com',
                'github_username' => 'mwebb-dev',
                'github_id' => '2345678',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/2345678',
            ]),
            User::factory()->create([
                'name' => 'Aiko Tanaka',
                'email' => 'aiko@example.com',
                'github_username' => 'aikot',
                'github_id' => '3456789',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/3456789',
            ]),
        ];

        foreach ($users as $user) {
            $this->seedUserDevicesAndProjects($user);
        }
    }

    private function seedUserDevicesAndProjects(User $user): void
    {
        // 2 devices per user: one online, one offline
        $onlineDevice = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
            'device_name' => 'hetzner-vps-'.$user->github_username,
            'os' => 'linux',
            'arch' => 'amd64',
            'chief_version' => '0.5.3',
        ]);

        $offlineDevice = DeviceAuthorization::factory()->offline()->create([
            'user_id' => $user->id,
            'device_name' => 'macbook-'.$user->github_username,
            'os' => 'darwin',
            'arch' => 'arm64',
            'chief_version' => '0.5.1',
        ]);

        // 4-6 projects per device with varied statuses
        $this->seedProjectsForDevice($onlineDevice, true);
        $this->seedProjectsForDevice($offlineDevice, false);

        // Create one cloud deployment for the first user
        if ($user->github_username === 'sarahchen') {
            CloudDeployment::factory()->hetzner()->create([
                'user_id' => $user->id,
                'device_authorization_id' => $onlineDevice->id,
            ]);
        }
    }

    private function seedProjectsForDevice(DeviceAuthorization $device, bool $isOnline): void
    {
        // Project 1: Mid-run with story progress (running)
        $runningProject = CachedProjectState::factory()->running()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'chief-uplink',
            'project_name' => 'Chief Uplink',
            'git_branch' => 'feat/websocket-relay',
            'last_commit_hash' => 'a3f8c21',
            'last_commit_message' => 'feat: US-016 - WebSocket Message Relay',
            'stories_completed' => 5,
            'stories_total' => 12,
            'active_sessions' => $isOnline ? 1 : 0,
        ]);

        // Run history for the running project
        RunHistory::factory()->completed()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'chief-uplink',
            'prd_name' => 'v1.0 Core Features',
            'stories_completed' => 8,
            'stories_total' => 8,
            'duration_seconds' => 2400,
            'tokens_used' => 285000,
            'started_at' => now()->subDays(5),
            'finished_at' => now()->subDays(5)->addMinutes(40),
        ]);

        // Log cache for the running project
        LogCache::factory()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'chief-uplink',
            'story_id' => 'US-006',
        ]);

        // Project 2: Completed (idle)
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'api-gateway',
            'project_name' => 'API Gateway',
            'git_branch' => 'main',
            'last_commit_hash' => 'b7e4d92',
            'last_commit_message' => 'feat: US-012 - Rate limiting middleware',
        ]);

        RunHistory::factory()->completed()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'api-gateway',
            'prd_name' => 'v1.0 API Gateway',
            'stories_completed' => 12,
            'stories_total' => 12,
            'duration_seconds' => 4800,
            'tokens_used' => 420000,
            'started_at' => now()->subDays(3),
            'finished_at' => now()->subDays(3)->addMinutes(80),
        ]);

        // Project 3: Failed (error)
        CachedProjectState::factory()->error()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'data-pipeline',
            'project_name' => 'Data Pipeline',
            'git_branch' => 'feat/streaming',
            'last_commit_hash' => 'c9f1e83',
            'last_commit_message' => 'feat: US-004 - Stream processing',
            'stories_completed' => 3,
            'stories_total' => 8,
        ]);

        RunHistory::factory()->failed()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'data-pipeline',
            'prd_name' => 'v2.0 Streaming',
            'stories_completed' => 3,
            'stories_total' => 8,
            'error_message' => 'Test suite failed: 3 tests failing in StreamProcessorTest',
            'started_at' => now()->subDays(1),
            'finished_at' => now()->subDays(1)->addMinutes(25),
        ]);

        // Project 4: No PRD
        CachedProjectState::factory()->noPrd()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'frontend-app',
            'project_name' => 'Frontend App',
            'git_branch' => 'main',
            'last_commit_hash' => 'd2a5f74',
            'last_commit_message' => 'chore: initial project setup',
        ]);

        // Project 5: Paused (only on online device for variety)
        if ($isOnline) {
            CachedProjectState::factory()->paused()->create([
                'device_authorization_id' => $device->id,
                'project_slug' => 'auth-service',
                'project_name' => 'Auth Service',
                'git_branch' => 'feat/oauth',
                'last_commit_hash' => 'e8b3c65',
                'last_commit_message' => 'feat: US-003 - OAuth provider setup',
                'stories_completed' => 4,
                'stories_total' => 10,
            ]);

            RunHistory::factory()->paused()->create([
                'device_authorization_id' => $device->id,
                'project_slug' => 'auth-service',
                'prd_name' => 'OAuth Integration',
                'stories_completed' => 4,
                'stories_total' => 10,
                'started_at' => now()->subHours(2),
            ]);
        }
    }
}
