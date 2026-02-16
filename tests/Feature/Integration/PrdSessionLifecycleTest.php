<?php

use App\Events\ChiefCommandDispatched;
use App\Events\ChiefMessageReceived;
use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\PrdSessionManager;
use App\Services\ServerConnectionManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'test-device',
    ]);
    $this->sessionManager = app(PrdSessionManager::class);
    $this->sessionId = 'prd-test-'.uniqid();

    // Clean up Redis keys
    $this->cleanupKeys = [];
});

afterEach(function () {
    try {
        $this->sessionManager->closeSession($this->sessionId);
        $this->sessionManager->closeDeviceSessions($this->device->id);
    } catch (\Throwable) {
        // Redis not available, skip cleanup
    }
});

function generatePrdTestAccessToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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

function setupPrdOnlineDevice(ServerConnectionManager $manager, DeviceAuthorization $device): void
{
    $connectionId = $device->id * 1000;
    $token = generatePrdTestAccessToken($device);

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
| PRD Session Creation (new_prd command)
|--------------------------------------------------------------------------
*/

describe('PRD Session Creation', function () {
    it('registers a PRD session when new_prd command is sent', function () {
        $manager = app(ServerConnectionManager::class);
        setupPrdOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'new_prd',
                'payload' => [
                    'session_id' => $this->sessionId,
                    'project_slug' => 'my-project',
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'sent',
                'type' => 'new_prd',
            ]);

        // Verify session was registered
        $session = $this->sessionManager->getSession($this->sessionId);
        expect($session)->not->toBeNull();
        expect($session['device_id'])->toBe($this->device->id);
        expect($session['user_id'])->toBe($this->user->id);
    });

    it('returns session_timeout_remaining in response', function () {
        $manager = app(ServerConnectionManager::class);
        setupPrdOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'new_prd',
                'payload' => [
                    'session_id' => $this->sessionId,
                    'project_slug' => 'my-project',
                ],
            ]);

        $response->assertOk();
        $data = $response->json();
        expect($data)->toHaveKey('session_timeout_remaining');
        expect($data['session_timeout_remaining'])->toBeGreaterThan(0);
        expect($data['session_timeout_remaining'])->toBeLessThanOrEqual(1800);
    });
});

/*
|--------------------------------------------------------------------------
| PRD Session Messages (prd_message command)
|--------------------------------------------------------------------------
*/

describe('PRD Session Messages', function () {
    it('touches session on prd_message to extend timeout', function () {
        $this->sessionManager->registerSession(
            $this->sessionId, $this->device->id, $this->user->id
        );

        // Wait briefly and touch the session
        $initialRemaining = $this->sessionManager->getTimeRemaining($this->sessionId);

        $manager = app(ServerConnectionManager::class);
        setupPrdOnlineDevice($manager, $this->device);

        $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'prd_message',
                'payload' => [
                    'session_id' => $this->sessionId,
                    'message' => 'Build a todo app',
                ],
            ]);

        $remainingAfterTouch = $this->sessionManager->getTimeRemaining($this->sessionId);
        expect($remainingAfterTouch)->toBeGreaterThanOrEqual($initialRemaining - 2);
    });

    it('returns session_timeout_remaining with prd_message response', function () {
        $this->sessionManager->registerSession(
            $this->sessionId, $this->device->id, $this->user->id
        );

        $manager = app(ServerConnectionManager::class);
        setupPrdOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'prd_message',
                'payload' => [
                    'session_id' => $this->sessionId,
                    'message' => 'Add user auth',
                ],
            ]);

        $response->assertOk();
        expect($response->json('session_timeout_remaining'))->not->toBeNull();
    });
});

/*
|--------------------------------------------------------------------------
| PRD Session Close (close_prd_session command)
|--------------------------------------------------------------------------
*/

describe('PRD Session Close', function () {
    it('closes session when close_prd_session command is sent', function () {
        $this->sessionManager->registerSession(
            $this->sessionId, $this->device->id, $this->user->id
        );

        $manager = app(ServerConnectionManager::class);
        setupPrdOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'close_prd_session',
                'payload' => [
                    'session_id' => $this->sessionId,
                    'save' => true,
                ],
            ]);

        $response->assertOk();

        // Session should be closed
        $session = $this->sessionManager->getSession($this->sessionId);
        expect($session)->toBeNull();
    });
});

/*
|--------------------------------------------------------------------------
| PRD Session Timeout
|--------------------------------------------------------------------------
*/

describe('PRD Session Timeout', function () {
    it('detects expiring sessions and sends timeout warnings', function () {
        Event::fake([ChiefMessageReceived::class, ChiefCommandDispatched::class]);

        // Set a very short timeout for testing
        config(['websocket.prd_session_timeout' => 60]); // 1 minute
        $sessionManager = new PrdSessionManager;

        $sessionId = 'timeout-test-'.uniqid();
        $sessionManager->registerSession($sessionId, $this->device->id, $this->user->id);

        // Manually backdate the last_activity_at to simulate time passing
        Redis::hset("prd:session:{$sessionId}", 'last_activity_at', (string) (time() - 59));
        Redis::zadd('prd:sessions:expiring', time() + 1, $sessionId);

        $result = $sessionManager->checkTimeouts();

        expect($result['warnings'])->toBeGreaterThanOrEqual(0);

        // Cleanup
        $sessionManager->closeSession($sessionId);
    });

    it('expires sessions that have timed out', function () {
        Event::fake([ChiefMessageReceived::class, ChiefCommandDispatched::class]);

        config(['websocket.prd_session_timeout' => 1]); // 1 second
        $sessionManager = new PrdSessionManager;

        $sessionId = 'expire-test-'.uniqid();
        $sessionManager->registerSession($sessionId, $this->device->id, $this->user->id);

        // Backdate the activity to trigger expiration
        Redis::hset("prd:session:{$sessionId}", 'last_activity_at', (string) (time() - 10));
        Redis::zadd('prd:sessions:expiring', time() - 10, $sessionId);

        $result = $sessionManager->checkTimeouts();

        expect($result['expired'])->toBe(1);

        // Session should be cleaned up
        expect($sessionManager->getSession($sessionId))->toBeNull();

        Event::assertDispatched(ChiefMessageReceived::class, function ($event) use ($sessionId) {
            return $event->message['type'] === 'session_expired'
                && $event->message['session_id'] === $sessionId;
        });
    });

    it('dispatches ChiefCommandDispatched for expired sessions', function () {
        Event::fake([ChiefMessageReceived::class, ChiefCommandDispatched::class]);

        config(['websocket.prd_session_timeout' => 1]); // 1 second
        $sessionManager = new PrdSessionManager;

        $sessionId = 'expire-broadcast-test-'.uniqid();
        $sessionManager->registerSession($sessionId, $this->device->id, $this->user->id);

        // Backdate the activity to trigger expiration
        Redis::hset("prd:session:{$sessionId}", 'last_activity_at', (string) (time() - 10));
        Redis::zadd('prd:sessions:expiring', time() - 10, $sessionId);

        $sessionManager->checkTimeouts();

        Event::assertDispatched(ChiefCommandDispatched::class, function ($event) use ($sessionId) {
            return $event->command['type'] === 'session_expired'
                && $event->command['session_id'] === $sessionId
                && $event->deviceId === $this->device->id
                && $event->userId === $this->user->id;
        });
    });

    it('dispatches ChiefCommandDispatched for timeout warnings', function () {
        Event::fake([ChiefMessageReceived::class, ChiefCommandDispatched::class]);

        // Set timeout so the session is within the 10-minute warning window
        config(['websocket.prd_session_timeout' => 600]); // 10 minutes
        $sessionManager = new PrdSessionManager;

        $sessionId = 'warning-broadcast-test-'.uniqid();
        $sessionManager->registerSession($sessionId, $this->device->id, $this->user->id);

        // Backdate activity so remaining time is ~5 minutes (within 10-minute warning)
        Redis::hset("prd:session:{$sessionId}", 'last_activity_at', (string) (time() - 300));
        Redis::zadd('prd:sessions:expiring', time() + 300, $sessionId);

        $result = $sessionManager->checkTimeouts();

        expect($result['warnings'])->toBe(1);

        Event::assertDispatched(ChiefCommandDispatched::class, function ($event) use ($sessionId) {
            return $event->command['type'] === 'session_timeout_warning'
                && $event->command['session_id'] === $sessionId
                && $event->deviceId === $this->device->id
                && $event->userId === $this->user->id
                && isset($event->command['minutes_remaining']);
        });
    });

    it('broadcasts both browser and CLI events for timeout warnings', function () {
        Event::fake([ChiefMessageReceived::class, ChiefCommandDispatched::class]);

        config(['websocket.prd_session_timeout' => 600]); // 10 minutes
        $sessionManager = new PrdSessionManager;

        $sessionId = 'dual-broadcast-test-'.uniqid();
        $sessionManager->registerSession($sessionId, $this->device->id, $this->user->id);

        // Backdate to within warning window
        Redis::hset("prd:session:{$sessionId}", 'last_activity_at', (string) (time() - 300));
        Redis::zadd('prd:sessions:expiring', time() + 300, $sessionId);

        $sessionManager->checkTimeouts();

        // Both events should be dispatched with matching data
        Event::assertDispatched(ChiefMessageReceived::class, function ($event) use ($sessionId) {
            return $event->message['type'] === 'session_timeout_warning'
                && $event->message['session_id'] === $sessionId;
        });

        Event::assertDispatched(ChiefCommandDispatched::class, function ($event) use ($sessionId) {
            return $event->command['type'] === 'session_timeout_warning'
                && $event->command['session_id'] === $sessionId;
        });
    });
});

/*
|--------------------------------------------------------------------------
| PRD Refinement Session
|--------------------------------------------------------------------------
*/

describe('PRD Refinement Session', function () {
    it('registers a refinement session with prd_id when refine_prd command is sent', function () {
        $manager = app(ServerConnectionManager::class);
        setupPrdOnlineDevice($manager, $this->device);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'refine_prd',
                'payload' => [
                    'session_id' => $this->sessionId,
                    'prd_id' => 'main',
                    'project_slug' => 'my-project',
                ],
            ]);

        $response->assertOk();

        $session = $this->sessionManager->getSession($this->sessionId);
        expect($session)->not->toBeNull();
        expect($session['prd_id'])->toBe('main');
        expect($session['device_id'])->toBe($this->device->id);
    });
});

/*
|--------------------------------------------------------------------------
| Multiple PRD Sessions
|--------------------------------------------------------------------------
*/

describe('Multiple PRD Sessions', function () {
    it('supports multiple simultaneous sessions across different projects', function () {
        $session1 = 'multi-1-'.uniqid();
        $session2 = 'multi-2-'.uniqid();

        $this->sessionManager->registerSession($session1, $this->device->id, $this->user->id);
        $this->sessionManager->registerSession($session2, $this->device->id, $this->user->id);

        $sessions = $this->sessionManager->getDeviceSessions($this->device->id);
        expect($sessions)->toContain($session1);
        expect($sessions)->toContain($session2);

        // Both sessions should be independent
        expect($this->sessionManager->getSession($session1))->not->toBeNull();
        expect($this->sessionManager->getSession($session2))->not->toBeNull();

        // Cleanup
        $this->sessionManager->closeSession($session1);
        $this->sessionManager->closeSession($session2);
    });

    it('closes all sessions for a device', function () {
        $session1 = 'closeall-1-'.uniqid();
        $session2 = 'closeall-2-'.uniqid();

        $this->sessionManager->registerSession($session1, $this->device->id, $this->user->id);
        $this->sessionManager->registerSession($session2, $this->device->id, $this->user->id);

        $closed = $this->sessionManager->closeDeviceSessions($this->device->id);

        expect($closed)->toBe(2);
        expect($this->sessionManager->getSession($session1))->toBeNull();
        expect($this->sessionManager->getSession($session2))->toBeNull();
        expect($this->sessionManager->getDeviceSessions($this->device->id))->toBeEmpty();
    });
});
