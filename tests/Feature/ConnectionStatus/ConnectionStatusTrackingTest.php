<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Connection Status in Inertia Props', function () {
    it('includes connection_status field for each device', function () {
        DeviceAuthorization::factory()->for($this->user)->online()->create();

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0])->toHaveKey('connection_status');
        });
    });

    it('online device shows green status', function () {
        DeviceAuthorization::factory()->for($this->user)->online()->create([
            'device_name' => 'my-server',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('online');
            expect($devices[0]['is_online'])->toBeTrue();
        });
    });

    it('recently disconnected device shows reconnecting status within 60s', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'device_name' => 'reconnecting-server',
            'is_online' => false,
            'last_connected_at' => now()->subSeconds(10),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('reconnecting');
        });
    });

    it('device disconnected over 60s ago shows offline status', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'device_name' => 'offline-server',
            'is_online' => false,
            'last_connected_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('offline');
        });
    });

    it('never connected device shows never-connected status', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'device_name' => 'new-server',
            'is_online' => false,
            'last_connected_at' => null,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('never-connected');
        });
    });

    it('offline device includes last_connected_at for relative time display', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['last_connected_at'])->not->toBeNull();
            expect($devices[0]['connection_status'])->toBe('offline');
        });
    });

    it('status is visible on project detail pages too', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device)->create([
            'project_slug' => 'my-project',
            'project_name' => 'My Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/my-project');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0])->toHaveKey('connection_status');
            expect($devices[0]['connection_status'])->toBe('online');
        });
    });

    it('boundary: device disconnected exactly at 60s shows offline', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => now()->subSeconds(60),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('offline');
        });
    });

    it('device disconnected at 59s shows reconnecting', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => now()->subSeconds(59),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('reconnecting');
        });
    });
});
