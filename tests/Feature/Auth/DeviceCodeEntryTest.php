<?php

use App\Models\OauthDeviceCode;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| GET /oauth/device — Device code entry page
|--------------------------------------------------------------------------
*/

test('device code entry page is accessible to authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/oauth/device');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('auth/DeviceCodeEntry'));
});

test('device code entry page redirects unauthenticated users to login', function () {
    $response = $this->get('/oauth/device');

    $response->assertRedirect('/login');
});

/*
|--------------------------------------------------------------------------
| POST /oauth/device/verify — Verify a user code
|--------------------------------------------------------------------------
*/

test('valid pending code redirects to confirmation page', function () {
    $user = User::factory()->create();
    $code = OauthDeviceCode::factory()->create([
        'user_code' => 'ABCD-1234',
    ]);

    $response = $this->actingAs($user)->post('/oauth/device/verify', [
        'user_code' => 'ABCD-1234',
    ]);

    $response->assertRedirect(route('oauth.device.confirm', ['code' => 'ABCD-1234']));
});

test('code verification is case-insensitive', function () {
    $user = User::factory()->create();
    $code = OauthDeviceCode::factory()->create([
        'user_code' => 'ABCD-1234',
    ]);

    $response = $this->actingAs($user)->post('/oauth/device/verify', [
        'user_code' => 'abcd-1234',
    ]);

    $response->assertRedirect(route('oauth.device.confirm', ['code' => 'ABCD-1234']));
});

test('invalid code returns error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/oauth/device/verify', [
        'user_code' => 'XXXX-YYYY',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['user_code']);
});

test('expired code returns error and marks code as expired', function () {
    $user = User::factory()->create();
    $code = OauthDeviceCode::factory()->create([
        'user_code' => 'ABCD-1234',
        'status' => 'pending',
        'expires_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($user)->post('/oauth/device/verify', [
        'user_code' => 'ABCD-1234',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['user_code']);

    $code->refresh();
    expect($code->status)->toBe('expired');
});

test('already approved code is not found', function () {
    $user = User::factory()->create();
    OauthDeviceCode::factory()->approved()->create([
        'user_code' => 'ABCD-1234',
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->post('/oauth/device/verify', [
        'user_code' => 'ABCD-1234',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['user_code']);
});

test('code verification requires valid format', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/oauth/device/verify', [
        'user_code' => 'ABC',
    ]);

    $response->assertSessionHasErrors(['user_code']);
});

/*
|--------------------------------------------------------------------------
| GET /oauth/device/confirm/{code} — Confirmation page
|--------------------------------------------------------------------------
*/

test('confirmation page shows device details', function () {
    $user = User::factory()->create();
    $code = OauthDeviceCode::factory()->create([
        'user_code' => 'ABCD-1234',
        'device_name' => 'macbook-pro',
    ]);

    $response = $this->actingAs($user)->get('/oauth/device/confirm/ABCD-1234');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/DeviceCodeEntry')
        ->has('confirmDevice')
        ->where('confirmDevice.device_name', 'macbook-pro')
        ->where('confirmDevice.user_code', 'ABCD-1234')
    );
});

test('confirmation page redirects if code is expired', function () {
    $user = User::factory()->create();
    OauthDeviceCode::factory()->create([
        'user_code' => 'ABCD-1234',
        'expires_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($user)->get('/oauth/device/confirm/ABCD-1234');

    $response->assertRedirect(route('oauth.device'));
});

test('confirmation page redirects if code is invalid', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/oauth/device/confirm/XXXX-YYYY');

    $response->assertRedirect(route('oauth.device'));
});

/*
|--------------------------------------------------------------------------
| POST /oauth/device/authorize — Authorize device
|--------------------------------------------------------------------------
*/

test('authorizing a device marks the code as approved', function () {
    $user = User::factory()->create();
    $code = OauthDeviceCode::factory()->create([
        'user_code' => 'ABCD-1234',
        'device_name' => 'dev-server',
    ]);

    $response = $this->actingAs($user)->post('/oauth/device/authorize', [
        'user_code' => 'ABCD-1234',
    ]);

    $response->assertRedirect(route('oauth.device'));
    $response->assertSessionHas('success', 'dev-server');

    $code->refresh();
    expect($code->status)->toBe('approved');
    expect($code->user_id)->toBe($user->id);
});

test('authorizing an expired code redirects with error', function () {
    $user = User::factory()->create();
    OauthDeviceCode::factory()->create([
        'user_code' => 'ABCD-1234',
        'expires_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($user)->post('/oauth/device/authorize', [
        'user_code' => 'ABCD-1234',
    ]);

    $response->assertRedirect(route('oauth.device'));
    $response->assertSessionHasErrors(['user_code']);
});

/*
|--------------------------------------------------------------------------
| POST /oauth/device/deny — Deny device
|--------------------------------------------------------------------------
*/

test('denying a device marks the code as denied', function () {
    $user = User::factory()->create();
    $code = OauthDeviceCode::factory()->create([
        'user_code' => 'ABCD-1234',
    ]);

    $response = $this->actingAs($user)->post('/oauth/device/deny', [
        'user_code' => 'ABCD-1234',
    ]);

    $response->assertRedirect(route('oauth.device'));

    $code->refresh();
    expect($code->status)->toBe('denied');
    expect($code->user_id)->toBe($user->id);
});

test('denying a nonexistent code redirects without error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/oauth/device/deny', [
        'user_code' => 'XXXX-YYYY',
    ]);

    $response->assertRedirect(route('oauth.device'));
});

/*
|--------------------------------------------------------------------------
| Rate limiting
|--------------------------------------------------------------------------
*/

test('device code entry is rate limited to 10 attempts per 15 minutes', function () {
    $user = User::factory()->create();

    // Make 10 attempts (the limit)
    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)->post('/oauth/device/verify', [
            'user_code' => sprintf('XX%02d-YYYY', $i),
        ]);
    }

    // 11th attempt should be rate limited
    $response = $this->actingAs($user)->post('/oauth/device/verify', [
        'user_code' => 'ZZZZ-YYYY',
    ]);

    $response->assertStatus(429);
});

/*
|--------------------------------------------------------------------------
| Integration: Full device auth flow
|--------------------------------------------------------------------------
*/

test('full device code entry flow: enter code → confirm → authorize → poll returns tokens', function () {
    $user = User::factory()->create();

    // Step 1: CLI requests a device code via API
    $codeResponse = $this->postJson('/api/oauth/device/code', [
        'device_name' => 'test-machine',
    ]);
    $codeResponse->assertOk();
    $userCode = $codeResponse->json('user_code');
    $deviceCode = $codeResponse->json('device_code');

    // Step 2: User enters the code on the web
    $verifyResponse = $this->actingAs($user)->post('/oauth/device/verify', [
        'user_code' => $userCode,
    ]);
    $verifyResponse->assertRedirect(route('oauth.device.confirm', ['code' => $userCode]));

    // Step 3: User sees the confirmation page
    $confirmResponse = $this->actingAs($user)->get("/oauth/device/confirm/$userCode");
    $confirmResponse->assertOk();
    $confirmResponse->assertInertia(fn ($page) => $page
        ->where('confirmDevice.device_name', 'test-machine')
    );

    // Step 4: User authorizes the device
    $authResponse = $this->actingAs($user)->post('/oauth/device/authorize', [
        'user_code' => $userCode,
    ]);
    $authResponse->assertRedirect(route('oauth.device'));
    $authResponse->assertSessionHas('success', 'test-machine');

    // Step 5: CLI polls and gets tokens
    $tokenResponse = $this->postJson('/api/oauth/device/token', [
        'device_code' => $deviceCode,
    ]);
    $tokenResponse->assertOk();
    $tokenResponse->assertJsonStructure([
        'access_token',
        'token_type',
        'expires_in',
        'refresh_token',
        'device_id',
    ]);
});
