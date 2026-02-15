<?php

use App\Events\DeviceConnected;
use App\Events\DeviceDisconnected;
use App\Http\Controllers\Api\DeviceOAuthController;
use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\ServerConnectionManager;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->manager = new ServerConnectionManager;
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->create([
        'is_online' => false,
        'device_name' => 'test-device',
        'os' => 'linux',
        'arch' => 'amd64',
        'chief_version' => '0.4.0',
    ]);
});

function generateTestAccessToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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

describe('Hello/Welcome Protocol', function () {
    it('authenticates with valid hello message', function () {
        Event::fake();

        $token = generateTestAccessToken($this->device);

        $result = $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.5.0',
            'device_name' => 'my-machine',
            'os' => 'linux',
            'arch' => 'amd64',
            'access_token' => $token,
        ]);

        expect($result['success'])->toBeTrue();
        expect($result['response']['type'])->toBe('welcome');
        expect($result['response']['protocol_version'])->toBe(1);
        expect($result['response']['device_id'])->toBe($this->device->id);
        expect($result['response']['connection_id'])->toBe(1);

        Event::assertDispatched(DeviceConnected::class, function ($event) {
            return $event->deviceId === $this->device->id
                && $event->userId === $this->user->id;
        });
    });

    it('rejects missing access token', function () {
        Event::fake();

        $result = $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
        ]);

        expect($result['success'])->toBeFalse();
        expect($result['response']['type'])->toBe('auth_failed');
        expect($result['response']['code'])->toBe('AUTH_FAILED');
        expect($result['response']['message'])->toBe('Access token is required.');

        Event::assertNotDispatched(DeviceConnected::class);
    });

    it('rejects invalid access token', function () {
        Event::fake();

        $result = $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => 'invalid-token',
        ]);

        expect($result['success'])->toBeFalse();
        expect($result['response']['type'])->toBe('auth_failed');
        expect($result['response']['code'])->toBe('AUTH_FAILED');
        expect($result['response']['message'])->toBe('Invalid or expired access token.');
    });

    it('rejects expired access token', function () {
        Event::fake();

        $token = generateTestAccessToken($this->device, -1);

        $result = $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        expect($result['success'])->toBeFalse();
        expect($result['response']['type'])->toBe('auth_failed');
        expect($result['response']['code'])->toBe('AUTH_FAILED');
        expect($result['response']['message'])->toBe('Invalid or expired access token.');
    });

    it('rejects revoked device', function () {
        Event::fake();

        $this->device->update(['revoked_at' => now()]);
        $token = generateTestAccessToken($this->device);

        $result = $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        expect($result['success'])->toBeFalse();
        expect($result['response']['type'])->toBe('auth_failed');
        expect($result['response']['code'])->toBe('AUTH_FAILED');
        expect($result['response']['message'])->toBe('Device has been revoked.');
    });

    it('rejects non-hello message type', function () {
        Event::fake();

        $result = $this->manager->handleHello(1, [
            'type' => 'subscribe',
            'channel' => 'some-channel',
        ]);

        expect($result['success'])->toBeFalse();
        expect($result['response']['type'])->toBe('auth_failed');
        expect($result['response']['code'])->toBe('AUTH_FAILED');
        expect($result['response']['message'])->toBe('Expected hello message.');
    });
});

describe('Device Status Updates', function () {
    it('marks device online on successful hello', function () {
        Event::fake();

        $token = generateTestAccessToken($this->device);

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.5.0',
            'device_name' => 'updated-name',
            'os' => 'darwin',
            'arch' => 'arm64',
            'access_token' => $token,
        ]);

        $this->device->refresh();
        expect($this->device->is_online)->toBeTrue();
        expect($this->device->last_connected_at)->not->toBeNull();
        expect($this->device->chief_version)->toBe('0.5.0');
        expect($this->device->os)->toBe('darwin');
        expect($this->device->arch)->toBe('arm64');
        expect($this->device->device_name)->toBe('updated-name');
    });

    it('marks device offline on disconnect', function () {
        Event::fake();

        $token = generateTestAccessToken($this->device);

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        $this->manager->handleDisconnect(1);

        $this->device->refresh();
        expect($this->device->is_online)->toBeFalse();

        Event::assertDispatched(DeviceDisconnected::class, function ($event) {
            return $event->deviceId === $this->device->id
                && $event->userId === $this->user->id;
        });
    });

    it('stores chief_version, os, and arch from hello message', function () {
        Event::fake();

        $token = generateTestAccessToken($this->device);

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'chief_version' => '0.6.0',
            'os' => 'windows',
            'arch' => 'x86_64',
            'access_token' => $token,
        ]);

        $this->device->refresh();
        expect($this->device->chief_version)->toBe('0.6.0');
        expect($this->device->os)->toBe('windows');
        expect($this->device->arch)->toBe('x86_64');
    });
});

describe('Connection Tracking', function () {
    it('tracks authenticated connections', function () {
        Event::fake();

        $token = generateTestAccessToken($this->device);

        expect($this->manager->isAuthenticated(1))->toBeFalse();

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        expect($this->manager->isAuthenticated(1))->toBeTrue();
        expect($this->manager->getDeviceId(1))->toBe($this->device->id);
        expect($this->manager->getUserId(1))->toBe($this->user->id);
    });

    it('removes connection on disconnect', function () {
        Event::fake();

        $token = generateTestAccessToken($this->device);

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        $this->manager->handleDisconnect(1);

        expect($this->manager->isAuthenticated(1))->toBeFalse();
        expect($this->manager->getDeviceId(1))->toBeNull();
        expect($this->manager->getUserId(1))->toBeNull();
    });

    it('handles disconnect of unauthenticated connection gracefully', function () {
        Event::fake();

        // Should not throw
        $this->manager->handleDisconnect(999);

        Event::assertNotDispatched(DeviceDisconnected::class);
    });

    it('replaces old connection when device reconnects', function () {
        Event::fake();

        $token = generateTestAccessToken($this->device);

        // First connection
        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        expect($this->manager->isAuthenticated(1))->toBeTrue();
        expect($this->manager->getConnectionIdForDevice($this->device->id))->toBe(1);

        // Second connection (same device, new connection)
        $this->manager->handleHello(2, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        expect($this->manager->isAuthenticated(1))->toBeFalse();
        expect($this->manager->isAuthenticated(2))->toBeTrue();
        expect($this->manager->getConnectionIdForDevice($this->device->id))->toBe(2);
    });

    it('tracks multiple device connections', function () {
        Event::fake();

        $device2 = DeviceAuthorization::factory()->for($this->user)->create();
        $token1 = generateTestAccessToken($this->device);
        $token2 = generateTestAccessToken($device2);

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token1,
        ]);

        $this->manager->handleHello(2, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token2,
        ]);

        expect($this->manager->isAuthenticated(1))->toBeTrue();
        expect($this->manager->isAuthenticated(2))->toBeTrue();
        expect($this->manager->getDeviceId(1))->toBe($this->device->id);
        expect($this->manager->getDeviceId(2))->toBe($device2->id);

        $connections = $this->manager->getActiveConnections();
        expect($connections)->toHaveCount(2);
    });

    it('can disconnect device by device ID', function () {
        Event::fake();

        $token = generateTestAccessToken($this->device);

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        $connectionId = $this->manager->disconnectDevice($this->device->id);

        expect($connectionId)->toBe(1);
        expect($this->manager->isAuthenticated(1))->toBeFalse();
        expect($this->manager->getConnectionIdForDevice($this->device->id))->toBeNull();
    });

    it('returns null when disconnecting non-existent device', function () {
        $connectionId = $this->manager->disconnectDevice(999);
        expect($connectionId)->toBeNull();
    });
});

describe('Token Refresh Detection', function () {
    it('detects connections needing token refresh', function () {
        Event::fake();

        // Token expiring in 2 minutes (within 5-minute threshold)
        $token = generateTestAccessToken($this->device, 120);

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        $needsRefresh = $this->manager->getConnectionsNeedingRefresh(300);
        expect($needsRefresh)->toContain(1);
    });

    it('does not flag connections with fresh tokens', function () {
        Event::fake();

        // Token expiring in 30 minutes (outside 5-minute threshold)
        $token = generateTestAccessToken($this->device, 1800);

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        $needsRefresh = $this->manager->getConnectionsNeedingRefresh(300);
        expect($needsRefresh)->toBeEmpty();
    });

    it('does not flag connections with already expired tokens', function () {
        Event::fake();

        // Use a token that expires in 1 second, then create connection
        $token = generateTestAccessToken($this->device, 1);

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        // Manually set token_expires_at to the past to simulate expiration
        // (We can't easily wait for 1 second in tests)
        $reflection = new ReflectionProperty($this->manager, 'connections');
        $connections = $reflection->getValue($this->manager);
        $connections[1]['token_expires_at'] = time() - 10;
        $reflection->setValue($this->manager, $connections);

        $needsRefresh = $this->manager->getConnectionsNeedingRefresh(300);
        expect($needsRefresh)->toBeEmpty();
    });
});

describe('Broadcast Events', function () {
    it('broadcasts DeviceConnected on successful hello', function () {
        Event::fake();

        $token = generateTestAccessToken($this->device);

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        Event::assertDispatched(DeviceConnected::class, function ($event) {
            return $event->deviceId === $this->device->id
                && $event->userId === $this->user->id;
        });
    });

    it('broadcasts DeviceDisconnected on disconnect', function () {
        Event::fake();

        $token = generateTestAccessToken($this->device);

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => $token,
        ]);

        $this->manager->handleDisconnect(1);

        Event::assertDispatched(DeviceDisconnected::class, function ($event) {
            return $event->deviceId === $this->device->id
                && $event->userId === $this->user->id;
        });
    });

    it('does not broadcast on failed hello', function () {
        Event::fake();

        $this->manager->handleHello(1, [
            'type' => 'hello',
            'protocol_version' => 1,
            'access_token' => 'invalid-token',
        ]);

        Event::assertNotDispatched(DeviceConnected::class);
    });

    it('DeviceConnected broadcasts on correct channel', function () {
        $event = new DeviceConnected(1, 42);

        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1);
        expect($channels[0]->name)->toBe('private-user.42');
        expect($event->broadcastAs())->toBe('device.connected');
    });

    it('DeviceDisconnected broadcasts on correct channel', function () {
        $event = new DeviceDisconnected(1, 42);

        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1);
        expect($channels[0]->name)->toBe('private-user.42');
        expect($event->broadcastAs())->toBe('device.disconnected');
    });
});

describe('Message Handling Before Auth', function () {
    it('rejects messages before hello authentication', function () {
        // This tests the ServerConnectionManager behavior
        // Messages before hello should not be processed
        expect($this->manager->isAuthenticated(1))->toBeFalse();

        // Calling handleHello with a non-hello type should fail
        $result = $this->manager->handleHello(1, [
            'type' => 'subscribe',
            'channel' => 'test',
        ]);

        expect($result['success'])->toBeFalse();
        expect($result['response']['type'])->toBe('auth_failed');
    });
});

describe('Access Token Validation', function () {
    it('validates correctly formed access tokens', function () {
        $token = generateTestAccessToken($this->device);
        $payload = DeviceOAuthController::validateAccessToken($token);

        expect($payload)->not->toBeNull();
        expect($payload['sub'])->toBe($this->device->user_id);
        expect($payload['did'])->toBe($this->device->id);
    });

    it('rejects tampered tokens', function () {
        $token = generateTestAccessToken($this->device);
        $parts = explode('.', $token);
        $parts[1] = 'tampered_signature';
        $tampered = implode('.', $parts);

        $payload = DeviceOAuthController::validateAccessToken($tampered);
        expect($payload)->toBeNull();
    });
});
