<?php

use App\Events\DeviceTokenRevoked;
use App\Models\DeviceAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('devices page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('devices.index'));

    $response->assertOk();
});

test('devices page lists authorized devices', function () {
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'device_name' => 'my-laptop',
        'os' => 'darwin',
        'arch' => 'arm64',
        'chief_version' => '0.5.3',
        'last_ip' => '192.168.1.1',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('devices.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/Devices')
        ->has('devices', 1)
        ->where('devices.0.id', $device->id)
        ->where('devices.0.device_name', 'my-laptop')
        ->where('devices.0.os', 'darwin')
        ->where('devices.0.arch', 'arm64')
        ->where('devices.0.chief_version', '0.5.3')
        ->where('devices.0.last_ip', '192.168.1.1')
        ->where('devices.0.is_online', true)
    );
});

test('devices page excludes revoked devices', function () {
    $user = User::factory()->create();
    DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'device_name' => 'active-device',
    ]);
    DeviceAuthorization::factory()->revoked()->create([
        'user_id' => $user->id,
        'device_name' => 'revoked-device',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('devices.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('devices', 1)
        ->where('devices.0.device_name', 'active-device')
    );
});

test('devices page only shows current users devices', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'device_name' => 'my-device',
    ]);
    DeviceAuthorization::factory()->create([
        'user_id' => $otherUser->id,
        'device_name' => 'other-device',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('devices.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('devices', 1)
        ->where('devices.0.device_name', 'my-device')
    );
});

test('devices page shows empty state when no devices', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('devices.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('devices', 0)
    );
});

test('online devices are listed before offline devices', function () {
    $user = User::factory()->create();
    DeviceAuthorization::factory()->offline()->create([
        'user_id' => $user->id,
        'device_name' => 'offline-device',
    ]);
    DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'device_name' => 'online-device',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('devices.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('devices', 2)
        ->where('devices.0.device_name', 'online-device')
        ->where('devices.1.device_name', 'offline-device')
    );
});

test('user can deauthorize their own device', function () {
    Event::fake([DeviceTokenRevoked::class]);

    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'device_name' => 'my-laptop',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('devices.destroy', $device->id));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $device->refresh();
    expect($device->revoked_at)->not->toBeNull();
    expect($device->is_online)->toBeFalse();
});

test('deauthorizing a device dispatches DeviceTokenRevoked event', function () {
    Event::fake([DeviceTokenRevoked::class]);

    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);

    $this
        ->actingAs($user)
        ->delete(route('devices.destroy', $device->id));

    Event::assertDispatched(DeviceTokenRevoked::class, function ($event) use ($device, $user) {
        return $event->deviceId === $device->id
            && $event->userId === $user->id;
    });
});

test('user cannot deauthorize another users device', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $device = DeviceAuthorization::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('devices.destroy', $device->id));

    $response->assertNotFound();

    expect($device->fresh()->revoked_at)->toBeNull();
});

test('user cannot deauthorize an already revoked device', function () {
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->revoked()->create([
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('devices.destroy', $device->id));

    $response->assertNotFound();
});

test('deauthorizing a device shows success message', function () {
    Event::fake([DeviceTokenRevoked::class]);

    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'device_name' => 'hetzner-vps',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('devices.destroy', $device->id));

    $response->assertSessionHas('success', 'Device "hetzner-vps" has been deauthorized.');
});

test('devices page requires authentication', function () {
    $response = $this->get(route('devices.index'));

    $response->assertRedirect(route('login'));
});

test('deauthorize action requires authentication', function () {
    $device = DeviceAuthorization::factory()->create();

    $response = $this->delete(route('devices.destroy', $device->id));

    $response->assertRedirect(route('login'));
});
