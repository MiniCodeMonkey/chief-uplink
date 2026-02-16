<?php

use App\Events\DeviceConnected;
use App\Events\DeviceDisconnected;
use App\Events\DeviceTokenRevoked;
use App\Models\DeviceAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Broadcast Auth Route', function () {
    it('exists and is accessible to authenticated users', function () {
        $this->actingAs($this->user)
            ->post('/broadcasting/auth', [
                'channel_name' => "private-user.{$this->user->id}",
            ])
            ->assertSuccessful();
    });

    it('redirects unauthenticated users to login', function () {
        $this->post('/broadcasting/auth', [
            'channel_name' => 'private-user.1',
        ])->assertRedirect('/login');
    });
});

describe('User Channel Authorization', function () {
    it('authorizes user for their own user channel via HTTP', function () {
        $this->actingAs($this->user)
            ->post('/broadcasting/auth', [
                'channel_name' => "private-user.{$this->user->id}",
            ])
            ->assertOk();
    });
});

describe('Device Channel Authorization', function () {
    it('authorizes user for their own device channel via HTTP', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->post('/broadcasting/auth', [
                'channel_name' => "private-device.{$device->id}",
            ])
            ->assertOk();
    });
});

describe('Chief Server Channel Authorization', function () {
    it('authorizes user for their own chief-server channel', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->create();

        $result = Broadcast::driver()->getChannels()['chief-server.{deviceId}'];

        expect($result($this->user, $device->id))->toBeTrue();
    });

    it('rejects user for another users device chief-server channel', function () {
        $otherUser = User::factory()->create();
        $otherDevice = DeviceAuthorization::factory()->for($otherUser)->create();

        $result = Broadcast::driver()->getChannels()['chief-server.{deviceId}'];

        expect($result($this->user, $otherDevice->id))->toBeFalse();
    });

    it('rejects revoked device chief-server channel', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->revoked()->create();

        $result = Broadcast::driver()->getChannels()['chief-server.{deviceId}'];

        expect($result($this->user, $device->id))->toBeFalse();
    });
});

describe('Broadcast Events', function () {
    it('DeviceConnected broadcasts to user channel', function () {
        $event = new DeviceConnected(1, $this->user->id);

        expect($event->broadcastOn())->toHaveCount(1);
        expect($event->broadcastOn()[0]->name)->toBe("private-user.{$this->user->id}");
        expect($event->broadcastAs())->toBe('device.connected');
    });

    it('DeviceDisconnected broadcasts to user channel', function () {
        $event = new DeviceDisconnected(1, $this->user->id);

        expect($event->broadcastOn())->toHaveCount(1);
        expect($event->broadcastOn()[0]->name)->toBe("private-user.{$this->user->id}");
        expect($event->broadcastAs())->toBe('device.disconnected');
    });

    it('DeviceTokenRevoked broadcasts to user and device channels', function () {
        $event = new DeviceTokenRevoked(42, $this->user->id);

        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(2);
        expect($channels[0]->name)->toBe("private-user.{$this->user->id}");
        expect($channels[1]->name)->toBe('private-device.42');
        expect($event->broadcastAs())->toBe('device.token.revoked');
    });

    it('DeviceConnected includes deviceId and userId in payload', function () {
        $event = new DeviceConnected(42, $this->user->id);

        expect($event->deviceId)->toBe(42);
        expect($event->userId)->toBe($this->user->id);
    });

    it('DeviceDisconnected includes deviceId and userId in payload', function () {
        $event = new DeviceDisconnected(42, $this->user->id);

        expect($event->deviceId)->toBe(42);
        expect($event->userId)->toBe($this->user->id);
    });

    it('DeviceTokenRevoked includes deviceId and userId in payload', function () {
        $event = new DeviceTokenRevoked(42, $this->user->id);

        expect($event->deviceId)->toBe(42);
        expect($event->userId)->toBe($this->user->id);
    });

    it('DeviceConnected implements ShouldBroadcast', function () {
        $event = new DeviceConnected(1, 1);
        expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
    });

    it('DeviceDisconnected implements ShouldBroadcast', function () {
        $event = new DeviceDisconnected(1, 1);
        expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
    });

    it('DeviceTokenRevoked implements ShouldBroadcast', function () {
        $event = new DeviceTokenRevoked(1, 1);
        expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
    });
});

describe('Connection Status in Inertia Props', function () {
    it('shares devices with connection status via Inertia', function () {
        DeviceAuthorization::factory()->for($this->user)->online()->create([
            'device_name' => 'online-server',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('devices'));
    });

    it('online device has online connection status', function () {
        DeviceAuthorization::factory()->for($this->user)->online()->create([
            'device_name' => 'online-server',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            $device = collect($devices)->firstWhere('device_name', 'online-server');
            expect($device['connection_status'])->toBe('online');
            expect($device['is_online'])->toBeTrue();
        });
    });

    it('offline device has offline connection status', function () {
        DeviceAuthorization::factory()->for($this->user)->offline()->create([
            'device_name' => 'offline-server',
            'last_connected_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            $device = collect($devices)->firstWhere('device_name', 'offline-server');
            expect($device['connection_status'])->toBe('offline');
        });
    });

    it('recently disconnected device shows reconnecting status', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'device_name' => 'reconnecting-server',
            'is_online' => false,
            'last_connected_at' => now()->subSeconds(30),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            $device = collect($devices)->firstWhere('device_name', 'reconnecting-server');
            expect($device['connection_status'])->toBe('reconnecting');
        });
    });

    it('device with no last_connected_at shows never-connected status', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'device_name' => 'new-server',
            'is_online' => false,
            'last_connected_at' => null,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            $device = collect($devices)->firstWhere('device_name', 'new-server');
            expect($device['connection_status'])->toBe('never-connected');
        });
    });

    it('does not include revoked devices in shared props', function () {
        DeviceAuthorization::factory()->for($this->user)->online()->create([
            'device_name' => 'active-server',
        ]);

        DeviceAuthorization::factory()->for($this->user)->revoked()->create([
            'device_name' => 'revoked-server',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(1);
            expect($devices[0]['device_name'])->toBe('active-server');
        });
    });

    it('does not include other users devices', function () {
        $otherUser = User::factory()->create();
        DeviceAuthorization::factory()->for($otherUser)->online()->create([
            'device_name' => 'other-user-server',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(0);
        });
    });

    it('includes device metadata in shared props', function () {
        DeviceAuthorization::factory()->for($this->user)->online()->create([
            'device_name' => 'my-server',
            'os' => 'linux',
            'arch' => 'amd64',
            'chief_version' => '0.5.0',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            $device = $devices[0];
            expect($device)->toHaveKeys([
                'id', 'device_name', 'os', 'arch', 'chief_version',
                'is_online', 'last_connected_at', 'connection_status', 'projects',
            ]);
            expect($device['device_name'])->toBe('my-server');
            expect($device['os'])->toBe('linux');
            expect($device['arch'])->toBe('amd64');
            expect($device['chief_version'])->toBe('0.5.0');
        });
    });
});
