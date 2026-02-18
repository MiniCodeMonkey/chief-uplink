<?php

namespace App\Console\Commands;

use App\Models\DeviceAuthorization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class E2ESetupCommand extends Command
{
    protected $signature = 'e2e:setup {--workspace=}';

    protected $description = 'Seed all test data for E2E tests: user, device, workspace with git repo, config, PRD files, and valid HMAC credentials';

    private const TEST_EMAIL = 'e2e-test@example.com';

    private const TEST_DEVICE_NAME = 'e2e-test-device';

    public function handle(): int
    {
        $workspace = $this->option('workspace');
        if (! $workspace) {
            $this->error('--workspace option is required');

            return Command::FAILURE;
        }

        $this->info('Setting up E2E test environment...');

        // 1. Create or find test user (idempotent)
        $user = User::where('email', self::TEST_EMAIL)->first();
        if ($user) {
            $this->info('Test user already exists, reusing.');
        } else {
            $user = User::factory()->create([
                'email' => self::TEST_EMAIL,
                'name' => 'E2E Test User',
            ]);
            $this->info('Created test user.');
        }

        // 2. Create or find device authorization (idempotent)
        $device = DeviceAuthorization::where('user_id', $user->id)
            ->where('device_name', self::TEST_DEVICE_NAME)
            ->whereNull('revoked_at')
            ->first();

        $refreshToken = Str::random(64);

        if ($device) {
            // Update refresh token so credentials file matches
            $device->update([
                'refresh_token_hash' => Hash::make($refreshToken),
            ]);
            $this->info('Device authorization already exists, updated refresh token.');
        } else {
            $device = DeviceAuthorization::create([
                'user_id' => $user->id,
                'device_name' => self::TEST_DEVICE_NAME,
                'refresh_token_hash' => Hash::make($refreshToken),
            ]);
            $this->info('Created device authorization.');
        }

        // 3. Generate HMAC access token (replicating DeviceOAuthController::generateAccessToken)
        $accessToken = $this->generateAccessToken($device);

        // 4. Create test workspace
        $this->createTestWorkspace($workspace);

        // 5. Write CLI credentials
        $wsUrl = $this->buildWsUrl();
        $this->writeCredentials($workspace, $accessToken, $refreshToken, $wsUrl);

        // 6. Output JSON with all generated IDs and paths
        $output = [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'device_id' => $device->id,
            'device_name' => self::TEST_DEVICE_NAME,
            'workspace' => $workspace,
            'project_path' => $workspace.'/projects/test-project',
            'credentials_path' => $workspace.'/.chief-home/.chief/credentials.yaml',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'ws_url' => $wsUrl,
        ];

        $this->line(json_encode($output));

        return Command::SUCCESS;
    }

    private function generateAccessToken(DeviceAuthorization $device): string
    {
        $payload = [
            'sub' => $device->user_id,
            'did' => $device->id,
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $payloadJson = json_encode($payload);
        $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));

        return $payloadBase64.'.'.$signature;
    }

    private function buildWsUrl(): ?string
    {
        $options = config('reverb.apps.apps.0.options', []);
        $host = $options['host'] ?? null;

        if (! $host) {
            return null;
        }

        $scheme = ($options['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws';
        $port = (int) ($options['port'] ?? ($scheme === 'wss' ? 443 : 80));
        $defaultPort = $scheme === 'wss' ? 443 : 80;
        $serverPath = config('reverb.servers.reverb.path', '');

        $url = "{$scheme}://{$host}";

        if ($port !== $defaultPort) {
            $url .= ":{$port}";
        }

        return "{$url}{$serverPath}/ws/server";
    }

    private function createTestWorkspace(string $workspace): void
    {
        $projectDir = $workspace.'/projects/test-project';

        // Create project directory
        if (! is_dir($projectDir)) {
            mkdir($projectDir, 0755, true);
        }

        // Initialize git repo if not already done
        if (! is_dir($projectDir.'/.git')) {
            $this->runGit($projectDir, 'init');
            $this->runGit($projectDir, 'config user.email "e2e@test.com"');
            $this->runGit($projectDir, 'config user.name "E2E Test"');

            file_put_contents($projectDir.'/README.md', "# Test Project\n\nE2E test project.\n");
            $this->runGit($projectDir, 'add README.md');
            $this->runGit($projectDir, 'commit -m "Initial commit"');
        }

        // Create .chief/config.yaml
        $chiefDir = $projectDir.'/.chief';
        if (! is_dir($chiefDir)) {
            mkdir($chiefDir, 0755, true);
        }

        file_put_contents($chiefDir.'/config.yaml', implode("\n", [
            'maxIterations: 5',
            'autoCommit: true',
            'commitPrefix: "feat:"',
            'claudeModel: "claude-sonnet-4-5-20250929"',
            'testCommand: "echo ok"',
            '',
        ]));

        // Create .chief/prds/feature-auth/
        $prdDir = $chiefDir.'/prds/feature-auth';
        if (! is_dir($prdDir)) {
            mkdir($prdDir, 0755, true);
        }

        file_put_contents($prdDir.'/prd.md', implode("\n", [
            '# Feature Auth',
            '',
            '## Overview',
            'Authentication feature for the test project.',
            '',
            '## User Stories',
            '1. User Login - Implement login form',
            '2. User Registration - Implement registration',
            '3. Password Reset - Implement password reset',
            '',
        ]));

        file_put_contents($prdDir.'/prd.json', json_encode([
            'project' => 'Feature Auth',
            'description' => 'Authentication feature for the test project.',
            'userStories' => [
                ['id' => 'US-001', 'title' => 'User Login', 'description' => 'Implement login form', 'acceptanceCriteria' => [], 'priority' => 1, 'passes' => true, 'inProgress' => false],
                ['id' => 'US-002', 'title' => 'User Registration', 'description' => 'Implement registration', 'acceptanceCriteria' => [], 'priority' => 2, 'passes' => false, 'inProgress' => false],
                ['id' => 'US-003', 'title' => 'Password Reset', 'description' => 'Implement password reset', 'acceptanceCriteria' => [], 'priority' => 3, 'passes' => false, 'inProgress' => false],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info("Created test workspace at {$projectDir}");
    }

    private function writeCredentials(string $workspace, string $accessToken, string $refreshToken, ?string $wsUrl): void
    {
        $credDir = $workspace.'/.chief-home/.chief';
        if (! is_dir($credDir)) {
            mkdir($credDir, 0755, true);
        }

        $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);

        $lines = [
            'access_token: "'.$accessToken.'"',
            'refresh_token: "'.$refreshToken.'"',
            'expires_at: "'.$expiresAt.'"',
            'device_name: "'.self::TEST_DEVICE_NAME.'"',
            'user: "'.self::TEST_EMAIL.'"',
        ];

        if ($wsUrl) {
            $lines[] = 'ws_url: "'.$wsUrl.'"';
        }

        $credPath = $credDir.'/credentials.yaml';
        file_put_contents($credPath, implode("\n", $lines)."\n");
        chmod($credPath, 0600);

        $this->info("Wrote credentials to {$credPath}");
    }

    private function runGit(string $dir, string $args): void
    {
        $command = 'cd '.escapeshellarg($dir)." && git {$args} 2>&1";
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            $this->warn("Git command failed: git {$args}");
            $this->warn(implode("\n", $output));
        }
    }
}
